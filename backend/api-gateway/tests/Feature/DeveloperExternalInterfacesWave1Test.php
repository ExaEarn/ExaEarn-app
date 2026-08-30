<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\DeveloperWebhookDelivery;
use App\Models\DeveloperWebhookEndpoint;
use App\Models\User;
use App\Services\DeveloperApiKeyService;
use App\Services\DeveloperWebhookEventRegistry;
use App\Services\DeveloperWebhookService;
use App\Services\Security\DnsResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DeveloperExternalInterfacesWave1Test extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(DnsResolver::class,new class extends DnsResolver { public function resolve(string $host): array{return ['93.184.216.34'];} });
    }

    public function test_webhook_environment_and_project_isolation_are_fail_closed(): void
    {
        $a=User::factory()->create();$b=User::factory()->create();
        $pa=app(DeveloperApiKeyService::class)->createProject($a->id,['name'=>'A','environment'=>'sandbox']);
        $pb=app(DeveloperApiKeyService::class)->createProject($b->id,['name'=>'B','environment'=>'sandbox']);
        $pa->environments()->where('type','production')->update(['status'=>'active']);
        $service=app(DeveloperWebhookService::class);
        $sandbox=$service->register($pa,['url'=>'https://sandbox.example.test/hook','events'=>['order.filled'],'environment'=>'sandbox'])['endpoint'];
        $production=$service->register($pa,['url'=>'https://production.example.test/hook','events'=>['order.filled'],'environment'=>'production'])['endpoint'];
        $other=$service->register($pb,['url'=>'https://other.example.test/hook','events'=>['order.filled'],'environment'=>'sandbox'])['endpoint'];
        $service->enqueue($pa,'order.filled',['order_id'=>'ord-sandbox'],'evt-sandbox','sandbox');
        $service->enqueue($pa,'order.filled',['order_id'=>'ord-production'],'evt-production','production');
        $this->assertDatabaseHas('developer_webhook_deliveries',['endpoint_id'=>$sandbox->id,'environment'=>'sandbox','event_id'=>'evt-sandbox']);
        $this->assertDatabaseHas('developer_webhook_deliveries',['endpoint_id'=>$production->id,'environment'=>'production','event_id'=>'evt-production']);
        $this->assertDatabaseMissing('developer_webhook_deliveries',['endpoint_id'=>$production->id,'event_id'=>'evt-sandbox']);
        $this->assertDatabaseMissing('developer_webhook_deliveries',['endpoint_id'=>$sandbox->id,'event_id'=>'evt-production']);
        $this->assertDatabaseMissing('developer_webhook_deliveries',['endpoint_id'=>$other->id,'event_id'=>'evt-sandbox']);
    }

    public function test_webhook_claim_is_exclusive_and_expired_claim_recovers(): void
    {
        Http::fake(['*'=>Http::response(['ok'=>true])]);
        $user=User::factory()->create();$project=app(DeveloperApiKeyService::class)->createProject($user->id,['name'=>'Claims','environment'=>'sandbox']);
        $service=app(DeveloperWebhookService::class);
        $service->register($project,['url'=>'https://claims.example.test/hook','events'=>['order.filled']]);
        $service->enqueue($project,'order.filled',['order_id'=>'ord-1'],'evt-claim');
        $first=$service->deliverDue();$second=$service->deliverDue();
        $this->assertSame(1,$first['delivered']);$this->assertSame(0,$second['delivered']);
        Http::assertSentCount(1);

        $delivery=DeveloperWebhookDelivery::query()->firstOrFail();
        $delivery->update(['status'=>'DELIVERING','claim_token'=>'11111111-1111-4111-8111-111111111111','claimed_at'=>now()->subMinutes(2),'claim_expires_at'=>now()->subMinute(),'delivered_at'=>null]);
        $recovered=$service->deliverDue();
        $this->assertSame(1,$recovered['delivered']);
    }

    public function test_event_registry_allows_contract_fields_and_redacts_secrets(): void
    {
        $registry=app(DeveloperWebhookEventRegistry::class);
        $safe=$registry->serialize('order.filled',['order_id'=>'ord-1','price'=>'100.00','api_secret'=>'no','password'=>'no','provider_credentials'=>'no','internal_debug'=>'no']);
        $this->assertSame(['order_id'=>'ord-1','price'=>'100.00'],$safe);
        $this->assertEqualsCanonicalizing(config('developer_api.webhooks.events'),$registry->events());
    }
}
