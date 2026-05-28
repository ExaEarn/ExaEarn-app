<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Services\ExaPointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use RuntimeException;
use Tests\TestCase;

class ExaPointEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_earning_updates_balance_and_ledger(): void
    {
        Redis::shouldReceive('publish')->once();

        $user = User::factory()->create();
        /** @var ExaPointService $service */
        $service = app(ExaPointService::class);

        $balance = $service->earn($user->id, '25', 'test:earn:1', 'earn test');

        $this->assertSame('25.00000000', $balance['available_points']);
        $this->assertSame('0.00000000', $balance['locked_points']);
        $this->assertSame('25.00000000', $balance['total_points']);

        $this->assertDatabaseHas('exapoint_transactions', [
            'user_id' => $user->id,
            'type' => 'earn',
            'reference' => 'test:earn:1',
        ]);
    }

    public function test_spend_deducts_without_negative_balance(): void
    {
        Redis::shouldReceive('publish')->times(2);

        $user = User::factory()->create();
        /** @var ExaPointService $service */
        $service = app(ExaPointService::class);

        $service->earn($user->id, '10', 'test:earn:2');
        $service->spend($user->id, '4', 'test:spend:2');

        $balance = $service->getBalance($user->id);
        $this->assertSame('6.00000000', $balance['available_points']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Insufficient available ExaPoints.');
        $service->spend($user->id, '100', 'test:spend:3');
    }

    public function test_lock_and_unlock_move_points_correctly(): void
    {
        Redis::shouldReceive('publish')->times(3);

        $user = User::factory()->create();
        /** @var ExaPointService $service */
        $service = app(ExaPointService::class);

        $service->earn($user->id, '12', 'test:earn:4');
        $service->lock($user->id, '5', 'test:lock:4');
        $service->unlock($user->id, '2', 'test:unlock:4');

        $balance = $service->getTotalExaPoints($user->id);
        $this->assertSame('9.00000000', $balance['available_points']);
        $this->assertSame('3.00000000', $balance['locked_points']);
        $this->assertSame('12.00000000', $balance['total_points']);
    }

    public function test_duplicate_reference_is_rejected(): void
    {
        Redis::shouldReceive('publish')->once();

        $user = User::factory()->create();
        /** @var ExaPointService $service */
        $service = app(ExaPointService::class);

        $service->earn($user->id, '5', 'test:dup:1');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Duplicate reward reference detected.');
        $service->earn($user->id, '5', 'test:dup:1');
    }
}

