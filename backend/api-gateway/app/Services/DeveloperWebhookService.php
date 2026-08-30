<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperProject;
use App\Models\DeveloperWebhookDelivery;
use App\Models\DeveloperWebhookEndpoint;
use App\Services\Security\WebhookDestinationValidator;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

class DeveloperWebhookService
{
    public function __construct(private readonly WebhookDestinationValidator $destinations,private readonly DeveloperWebhookEventRegistry $events) {}

    public function register(DeveloperProject $project, array $payload): array
    {
        $destination = $this->destinations->validate((string) $payload['url']);
        $environment=strtolower((string)($payload['environment']??'sandbox'));
        $environmentRow=$project->environments()->where('type',$environment)->first();
        if(!$environmentRow || $environmentRow->status!=='active') throw new RuntimeException('Webhook environment is not active.');

        $secret = 'whsec_' . Str::random(64);
        $endpoint = DeveloperWebhookEndpoint::query()->create([
            'endpoint_uuid' => (string) Str::uuid(),
            'user_id' => $project->user_id,
            'project_id' => $project->id,
            'url' => $destination['url'],
            'environment' => $environment,
            'status' => 'active',
            'events' => array_values(array_unique((array) $payload['events'])),
            'encrypted_secret' => Crypt::encryptString($secret),
            'secret_rotated_at' => now(),
        ]);

        return ['endpoint' => $endpoint, 'signing_secret' => $secret];
    }

    public function rotateSecret(DeveloperWebhookEndpoint $endpoint): array
    {
        $secret = 'whsec_' . Str::random(64);
        $endpoint->update([
            'encrypted_secret' => Crypt::encryptString($secret),
            'secret_rotated_at' => now(),
        ]);

        return ['endpoint' => $endpoint->fresh(), 'signing_secret' => $secret];
    }

    public function enqueue(DeveloperProject $project, string $eventType, array $payload, ?string $eventId = null,?string $eventEnvironment=null): int
    {
        $eventId ??= (string) Str::uuid();
        $environment=$eventEnvironment?strtolower($eventEnvironment):$this->projectEnvironment($project);
        if(!in_array($environment,['sandbox','production'],true)) throw new RuntimeException('Webhook event environment is invalid.');
        $safe=$this->events->serialize($eventType,$payload);
        $endpoints = DeveloperWebhookEndpoint::query()
            ->where('project_id', $project->id)
            ->where('environment',$environment)
            ->where('status', 'active')
            ->get()
            ->filter(fn (DeveloperWebhookEndpoint $endpoint): bool => in_array($eventType, (array) $endpoint->events, true));

        foreach ($endpoints as $endpoint) {
            DeveloperWebhookDelivery::query()->create([
                'delivery_uuid' => (string) Str::uuid(),
                'event_id' => $eventId,
                'endpoint_id' => $endpoint->id,
                'project_id'=>$project->id,'environment'=>$environment,
                'event_type' => $eventType,
                'payload' => $this->safePayload($eventId, $eventType, $safe,$project,$environment),
                'attempts' => 0,
                'status' => 'PENDING',
                'next_attempt_at' => now(),
            ]);
        }

        return $endpoints->count();
    }

    public function deliverDue(int $limit = 50): array
    {
        $results = ['delivered' => 0, 'retrying' => 0, 'dead_lettered' => 0, 'failed' => 0];
        $deliveries=$this->claimDue($limit);

        foreach ($deliveries as $delivery) {
            $this->deliver($delivery);
            $status = strtolower((string) $delivery->fresh()->status);
            if (isset($results[$status])) {
                $results[$status]++;
            }
        }

        return $results;
    }

    public function replay(DeveloperWebhookDelivery $delivery): DeveloperWebhookDelivery
    {
        return DB::transaction(function () use ($delivery): DeveloperWebhookDelivery {
            return DeveloperWebhookDelivery::query()->create([
                'delivery_uuid' => (string) Str::uuid(),
                'event_id' => $delivery->event_id,
                'endpoint_id' => $delivery->endpoint_id,
                'project_id'=>$delivery->project_id,'environment'=>$delivery->environment,
                'event_type' => $delivery->event_type,
                'payload' => $delivery->payload,
                'attempts' => 0,
                'status' => 'PENDING',
                'next_attempt_at' => now(),
            ]);
        });
    }

    public function signature(string $secret, int $timestamp, string $body): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    private function deliver(DeveloperWebhookDelivery $delivery): void
    {
        /** @var DeveloperWebhookEndpoint|null $endpoint */
        $endpoint = $delivery->endpoint;
        $project = DeveloperProject::query()->with('environments')->find($delivery->project_id);
        $environment = $project?->environments->firstWhere('type', $delivery->environment);
        if (! $endpoint || ! $project || $project->status !== 'active' || ! $environment || $environment->status !== 'active'
            || $endpoint->status !== 'active' || (int)$endpoint->project_id!==(int)$delivery->project_id || $endpoint->environment!==$delivery->environment) {
            $delivery->update(['status' => 'DEAD_LETTERED', 'dead_lettered_at' => now(), 'last_error' => 'Endpoint inactive.']);
            return;
        }

        try {
            $destination = $this->destinations->validate((string)$endpoint->url);
        } catch (\Throwable) {
            $delivery->update(['status'=>'DEAD_LETTERED','dead_lettered_at'=>now(),'last_error'=>'Webhook destination failed security validation.']);
            return;
        }
        if ($delivery->environment === 'production' && (! (bool)config('developer_api.webhooks.production_delivery_enabled', false)
            || ! (bool)config('developer_api.webhooks.production_egress_verified', false))) {
            $delivery->update(['status'=>'DEAD_LETTERED','dead_lettered_at'=>now(),'last_error'=>'Production webhook delivery is not enabled.']);
            return;
        }
        $delivery->update(['status' => 'DELIVERING']);
        $body = json_encode($delivery->payload, JSON_THROW_ON_ERROR);
        $timestamp = now()->timestamp;
        $secret = Crypt::decryptString((string) $endpoint->encrypted_secret);

        try {
            $resolve = $destination['host'].':'.$destination['port'].':'.$destination['pinned_address'];
            $response = Http::timeout((int) config('developer_api.webhooks.timeout_seconds', 5))
                ->withOptions(['allow_redirects'=>false,'curl'=>[CURLOPT_RESOLVE=>[$resolve]]])
                ->withHeaders([
                    'Content-Type' => 'application/json',
                    'X-ExaEarn-Event-Id' => (string) $delivery->event_id,
                    'X-ExaEarn-Timestamp' => (string) $timestamp,
                    'X-ExaEarn-Signature' => $this->signature($secret, $timestamp, $body),
                ])
                ->send('POST', $destination['url'], ['body' => $body]);

            if ($response->successful()) {
                $delivery->update([
                    'status' => 'DELIVERED',
                    'attempts' => $delivery->attempts + 1,
                    'last_status_code' => $response->status(),
                    'delivered_at' => now(),
                    'last_error' => null,'claim_token'=>null,'claimed_at'=>null,'claim_expires_at'=>null,
                ]);
                $endpoint->update(['last_delivered_at' => now()]);
                return;
            }

            $this->markRetry($delivery, $response->status(), 'Webhook endpoint returned HTTP ' . $response->status());
        } catch (\Throwable $exception) {
            $this->markRetry($delivery, null, 'Webhook delivery failed.');
        }
    }

    private function markRetry(DeveloperWebhookDelivery $delivery, ?int $statusCode, string $error): void
    {
        $attempts = $delivery->attempts + 1;
        if ($attempts >= (int) config('developer_api.webhooks.max_attempts', 8)) {
            $delivery->update([
                'status' => 'DEAD_LETTERED',
                'attempts' => $attempts,
                'last_status_code' => $statusCode,
                'last_error' => $error,
                'dead_lettered_at' => now(),
                'claim_token'=>null,'claimed_at'=>null,'claim_expires_at'=>null,
            ]);
            return;
        }

        $delay = min(3600, 2 ** $attempts * 30);
        $delivery->update([
            'status' => 'RETRYING',
            'attempts' => $attempts,
            'last_status_code' => $statusCode,
            'last_error' => $error,
            'next_attempt_at' => now()->addSeconds($delay),
            'claim_token'=>null,'claimed_at'=>null,'claim_expires_at'=>null,
        ]);
    }

    private function safePayload(string $eventId, string $eventType, array $payload,DeveloperProject $project,string $environment): array
    {
        return [
            'event_id' => $eventId,
            'event_type' => $eventType,
            'version'=>'1.0','project'=>$project->project_uuid,'environment'=>$environment,
            'created_at' => now()->toISOString(),
            'data' => $payload,
        ];
    }

    private function projectEnvironment(DeveloperProject $project): string
    {
        $environment=strtolower((string)($project->environment ?: 'sandbox'));
        return in_array($environment,['sandbox','production'],true)?$environment:'sandbox';
    }

    private function claimDue(int $limit)
    {
        return DB::transaction(function()use($limit){
            DeveloperWebhookDelivery::query()->where('status','DELIVERING')->where('claim_expires_at','<=',now())->update(['status'=>'RETRYING','claim_token'=>null,'claimed_at'=>null,'claim_expires_at'=>null,'next_attempt_at'=>now()]);
            $query=DeveloperWebhookDelivery::query()->whereIn('status',['PENDING','RETRYING','FAILED'])->where(fn($q)=>$q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()))->orderBy('created_at')->limit($limit);
            $query->lock(DB::connection()->getDriverName()==='pgsql'?'for update skip locked':true);
            $rows=$query->get();
            foreach($rows as $row){$token=(string)Str::uuid();$row->update(['status'=>'DELIVERING','claim_token'=>$token,'claimed_at'=>now(),'claim_expires_at'=>now()->addSeconds((int)config('developer_api.webhooks.claim_lease_seconds',60))]);}
            return DeveloperWebhookDelivery::query()->with('endpoint')->whereIn('id',$rows->pluck('id'))->get();
        });
    }
}
