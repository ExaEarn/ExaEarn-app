<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\DeveloperProject;
use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiRealtimeSession;
use App\Models\DeveloperRealtimeEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;

class DeveloperRealtimeService
{
    public function __construct(private readonly DeveloperProductionAccessService $productionAccess) {}

    public function createSession(DeveloperProject $project, DeveloperApiKey $key, array $topics): array
    {
        $normalized = $this->validateTopics($topics);
        $permissions=$key->permissions()->pluck('permission')->all();
        $required=array_values(array_unique(array_filter(array_map(fn($topic)=>$this->scopeForTopic((string)$topic),$normalized))));
        foreach($required as $scope) if(!in_array($scope,$permissions,true)) throw new \RuntimeException("WebSocket topic requires scope: {$scope}");
        if($key->environment==='production') $this->productionAccess->assertCapabilities($project,$required);

        $token='devws_'.bin2hex(random_bytes(32));$expires=now()->addMinutes(10);
        DeveloperApiRealtimeSession::query()->create(['session_uuid'=>(string)Str::uuid(),'api_key_id'=>$key->id,'project_id'=>$project->id,'environment'=>$key->environment,'token_hash'=>hash('sha256',$token),'status'=>'active','topics'=>$normalized,'expires_at'=>$expires]);
        return [
            'session_id' => $token,
            'environment' => $key->environment,
            'heartbeat_seconds' => (int) config('developer_api.websocket.heartbeat_seconds', 30),
            'max_subscriptions' => (int) config('developer_api.websocket.max_subscriptions', 100),
            'queue_limit' => (int) config('developer_api.websocket.queue_limit', 1000),
            'topics' => $normalized,
            'replay_url' => '/api/developer/v1/realtime/replay',
            'expires_at' => $expires->toISOString(),
        ];
    }

    public function validSession(string $token): ?DeveloperApiRealtimeSession
    {
        return DeveloperApiRealtimeSession::query()->where('token_hash',hash('sha256',$token))->where('status','active')->where('expires_at','>',now())->whereHas('apiKey',fn($query)=>$query->where('status','active'))->first();
    }

    public function authorizeTransport(string $token,string $environment): array
    {
        $session=DeveloperApiRealtimeSession::query()->with(['apiKey.permissions','apiKey.project.environments','apiKey.project.workspace','apiKey.project.organization','project'])->where('token_hash',hash('sha256',$token))->where('status','active')->where('expires_at','>',now())->first();
        if(!$session || !$session->apiKey || !$session->project || $session->environment!==$environment || $session->apiKey->environment!==$environment) throw new \RuntimeException('REALTIME_SESSION_INVALID');
        $key=$session->apiKey;$project=$session->project;
        if($key->status!=='active' || $project->status!=='active' || $project->workspace?->status!=='active' || ($project->organization_id && $project->organization?->status!=='active')) throw new \RuntimeException('REALTIME_PARENT_INACTIVE');
        $env=$project->environments->firstWhere('type',$environment);
        if(!$env || $env->status!=='active') throw new \RuntimeException('REALTIME_ENVIRONMENT_INACTIVE');
        $permissions=$key->permissions->pluck('permission')->all();
        foreach((array)$session->topics as $topic){$required=$this->scopeForTopic((string)$topic);if($required && !in_array($required,$permissions,true)) throw new \RuntimeException('REALTIME_SCOPE_DENIED');}
        if($environment==='production') $this->productionAccess->assertCapabilities($project,array_values(array_unique(array_filter(array_map(fn($topic)=>$this->scopeForTopic((string)$topic),(array)$session->topics)))));
        return ['session_uuid'=>$session->session_uuid,'project_uuid'=>$project->project_uuid,'project_id'=>$project->id,'api_key_uuid'=>$key->key_uuid,'environment'=>$environment,'topics'=>$session->topics,'expires_at'=>$session->expires_at?->toISOString()];
    }

    public function transportReplay(string $token,string $environment,string $stream,int $after,int $limit): array
    {
        $authority=$this->authorizeTransport($token,$environment);
        if(!in_array($stream,(array)$authority['topics'],true)) throw new \RuntimeException('REALTIME_SCOPE_DENIED');
        $events=$this->replay(DeveloperProject::query()->findOrFail($authority['project_id']),$stream,$after,$limit,$environment);
        $latest=DeveloperRealtimeEvent::query()->where('project_id',$authority['project_id'])->where('environment',$environment)->where('stream',$stream)->max('sequence');
        return ['events'=>$events,'latest_sequence'=>(int)$latest,'reconcile_required'=>$events!==[] && (int)$events[0]['sequence']!==$after+1];
    }

    private function scopeForTopic(string $topic): ?string
    {
        return match(true){
            str_starts_with($topic,'market.')=>null,
            $topic==='order'||$topic==='trade'=>'spot.read',
            $topic==='position'=>'futures.read',
            $topic==='margin'=>'margin.read',
            $topic==='staking'=>'staking.read',
            $topic==='copy'=>'copy.read',
            $topic==='exaai'=>'exaai.read',
            default=>'account.read',
        };
    }

    public function publish(DeveloperProject $project, string $stream, string $eventType, array $payload,?string $environment=null): DeveloperRealtimeEvent
    {
        $environment=strtolower((string)($environment?:$project->environment?:'sandbox'));
        $event=DB::transaction(function () use ($eventType, $payload, $project, $stream,$environment): DeveloperRealtimeEvent {
            DeveloperProject::query()->whereKey($project->id)->lockForUpdate()->firstOrFail();
            $last = DeveloperRealtimeEvent::query()
                ->where('project_id', $project->id)
                ->where('environment', $environment)
                ->where('stream', $stream)
                ->max('sequence');

            return DeveloperRealtimeEvent::query()->create([
                'event_id' => (string) Str::uuid(),
                'user_id' => $project->user_id,
                'project_id' => $project->id,
                'environment' => $environment,
                'stream' => $stream,
                'sequence' => ((int) $last) + 1,
                'event_type' => $eventType,
                'payload' => $payload,
                'created_at' => now(),
            ]);
        });
        try{Redis::publish((string)config('developer_api.websocket.redis_channel','exaearn.developer.realtime'),json_encode(['project_id'=>$project->id,'environment'=>$environment,'event_id'=>$event->event_id,'stream'=>$event->stream,'sequence'=>$event->sequence,'event_type'=>$event->event_type,'payload'=>$event->payload,'timestamp'=>$event->created_at?->toISOString()],JSON_THROW_ON_ERROR));}catch(\Throwable){}
        return $event;
    }

    public function replay(DeveloperProject $project, string $stream, int $afterSequence, int $limit = 500,?string $environment=null): array
    {
        $limit = min(max($limit, 1), (int) config('developer_api.websocket.replay_limit', 500));

        return DeveloperRealtimeEvent::query()
            ->where('project_id', $project->id)
            ->when($environment,fn($query)=>$query->where('environment',$environment))
            ->where('stream', $stream)
            ->where('sequence', '>', $afterSequence)
            ->orderBy('sequence')
            ->limit($limit)
            ->get()
            ->map(fn (DeveloperRealtimeEvent $event): array => [
                'event_id' => $event->event_id,
                'stream' => $event->stream,
                'sequence' => $event->sequence,
                'event_type' => $event->event_type,
                'payload' => $event->payload,
                'timestamp' => $event->created_at?->toISOString(),
            ])
            ->all();
    }

    public function validateTopics(array $topics): array
    {
        $max = (int) config('developer_api.websocket.max_subscriptions', 100);
        if (count($topics) > $max) {
            throw new \RuntimeException('Too many websocket subscriptions requested.');
        }

        $allowed = (array) config('developer_api.websocket.allowed_topics', []);
        $normalized = [];
        foreach ($topics as $topic) {
            $topic = trim((string) $topic);
            if ($topic === '' || ! preg_match('/^[a-z0-9_.:-]+$/i', $topic)) {
                throw new \RuntimeException('Invalid websocket topic.');
            }
            $matched = false;
            foreach ($allowed as $pattern) {
                if (fnmatch((string) $pattern, $topic)) {
                    $matched = true;
                    break;
                }
            }
            if (! $matched) {
                throw new \RuntimeException("Unsupported websocket topic: {$topic}");
            }
            $normalized[] = $topic;
        }

        return array_values(array_unique($normalized));
    }
}
