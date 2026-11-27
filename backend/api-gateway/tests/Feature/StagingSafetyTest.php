<?php

namespace Tests\Feature;

use Tests\TestCase;

class StagingSafetyTest extends TestCase
{
    public function test_staging_rejects_login_with_the_financial_safety_lock_code(): void
    {
        config()->set('app.env', 'staging');
        config()->set('staging_safety.deployment_env', 'staging');
        config()->set('staging_safety.enabled', true);
        config()->set('staging_safety.lock', 'locked');

        $response = $this->postJson('/api/auth/login', [
            'email' => 'staging-user@example.invalid',
            'password' => 'not-a-real-password',
        ]);

        $response->assertForbidden()
            ->assertJsonPath('code', 'STAGING_FINANCIAL_SAFETY_LOCK');
    }
}
