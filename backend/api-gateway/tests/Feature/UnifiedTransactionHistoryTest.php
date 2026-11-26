<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class UnifiedTransactionHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_mine_history_is_account_scoped_and_supports_search_and_date_filters(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createTransaction($user->id, 'tx-owner-1', 'deposit-alpha', '2025-01-10 10:00:00');
        $this->createTransaction($otherUser->id, 'tx-other-1', 'deposit-alpha', '2025-01-10 10:00:00');
        $this->createTransaction($user->id, 'tx-owner-old', 'deposit-alpha-old', '2024-12-10 10:00:00');

        Sanctum::actingAs($user);

        $this->getJson('/api/transactions/mine?search=ALPHA&from=2025-01-01&to=2025-01-31')
            ->assertOk()
            ->assertJsonCount(1, 'data.data')
            ->assertJsonPath('data.data.0.transaction_id', 'tx-owner-1');
    }

    public function test_mine_history_requires_an_authenticated_user(): void
    {
        $environment = app()->environment();
        app()->detectEnvironment(static fn (): string => 'production');

        try {
            $this->getJson('/api/transactions/mine?user_id=1')
                ->assertUnauthorized();
        } finally {
            app()->detectEnvironment(static fn (): string => $environment);
        }
    }

    public function test_cursor_history_applies_filters_and_never_returns_another_users_rows(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        $this->createTransaction($user->id, 'cursor-owner-a', 'coffee-a', '2025-02-02 10:00:00', 'deposit', '12.50000000', [
            'merchant' => 'Coffee House', 'network' => 'polygon', 'internal_admin_note' => 'must not reach mobile',
        ]);
        $this->createTransaction($user->id, 'cursor-owner-b', 'coffee-b', '2025-02-02 10:00:00', 'deposit', '8.00000000', [
            'merchant' => 'Coffee House', 'network' => 'polygon',
        ]);
        $this->createTransaction($otherUser->id, 'cursor-other', 'coffee-other', '2025-02-02 10:00:00', 'deposit', '9.00000000', [
            'merchant' => 'Coffee House', 'network' => 'polygon',
        ]);

        Sanctum::actingAs($user);
        $filters = 'cursor_mode=1&per_page=1&search=coffee&direction=credit&network=polygon&currency=USDT&min_amount=1&max_amount=20';
        $first = $this->getJson('/api/transactions/mine?' . $filters)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_id', 'cursor-owner-b')
            ->assertJsonPath('data.0.merchant', 'Coffee House')
            ->assertJsonMissingPath('data.0.metadata')
            ->assertJsonPath('meta.has_more', true);
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotEmpty($cursor);

        $this->getJson('/api/transactions/mine?' . $filters . '&cursor=' . urlencode($cursor))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.transaction_id', 'cursor-owner-a')
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_mine_history_rejects_invalid_amount_ranges(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->getJson('/api/transactions/mine?min_amount=10&max_amount=2')
            ->assertUnprocessable();
    }

    private function createTransaction(
        int $userId,
        string $transactionId,
        string $reference,
        string $createdAt,
        string $type = 'deposit',
        string $amount = '12.50000000',
        array $metadata = [],
    ): void
    {
        $transaction = Transaction::query()->create([
            'transaction_id' => $transactionId,
            'user_id' => $userId,
            'type' => $type,
            'currency' => 'USDT',
            'amount' => $amount,
            'fee' => '0',
            'status' => 'completed',
            'reference' => $reference,
            'metadata' => $metadata,
        ]);
        $transaction->created_at = $createdAt;
        $transaction->save();
    }
}
