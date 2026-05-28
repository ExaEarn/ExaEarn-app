<?php

namespace Tests\Feature\Feature;

use App\Models\User;
use App\Models\VirtualAccount;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VirtualAccountFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_gets_account_on_registration()
    {
        // Mock user creation
        $user = User::factory()->create();
        
        $service = new \App\Services\VirtualAccountService();
        $account = $service->create($user);

        $this->assertNotNull($account);
        $this->assertEquals($user->id, $account->user_id);
        $this->assertDatabaseHas('virtual_accounts', [
            'user_id' => $user->id,
            'account_number' => '1234567890'
        ]);
    }
}
