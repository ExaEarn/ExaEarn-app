<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

final class ServiceCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_catalog_exposes_only_safe_route_and_service_metadata(): void
    {
        $this->getJson('/api/public/services')
            ->assertOk()
            ->assertJsonPath('data.quick_access_defaults', ['exapay', 'p2p', 'rewards'])
            ->assertJsonPath('data.services.0.id', 'exapay')
            ->assertJsonPath('data.services.0.route', '/exapay')
            ->assertJsonPath('data.services.0.available', true)
            ->assertJsonMissingPath('data.services.0.compliance_product_code')
            ->assertJsonMissingPath('data.quick_access');

        $services = $this->getJson('/api/public/services')->json('data.services');
        foreach ($services as $service) {
            $this->assertStringStartsWith('/', $service['route']);
            $this->assertFalse(str_starts_with($service['route'], '//'));
            $this->assertArrayNotHasKey('availability_flag', $service);
            $this->assertArrayNotHasKey('compliance_product_code', $service);
        }
    }

    public function test_personalized_catalog_requires_authentication(): void
    {
        $this->getJson('/api/me/services')->assertUnauthorized();
    }

    public function test_personal_catalog_filters_unavailable_shortcuts_without_mutating_preferences(): void
    {
        $user = User::factory()->create([
            'preferences' => ['dashboard' => ['quick_access_services' => ['exacard', 'p2p']]],
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me/services')
            ->assertOk()
            ->assertJsonPath('data.quick_access.services', ['p2p'])
            ->assertJsonPath('data.services.1.id', 'exacard')
            ->assertJsonPath('data.services.1.available', false);

        $this->assertSame(
            ['exacard', 'p2p'],
            $user->fresh()->preferences['dashboard']['quick_access_services'],
        );
    }

    public function test_personalized_catalog_does_not_cache_preferences_between_users(): void
    {
        $first = User::factory()->create([
            'preferences' => ['dashboard' => ['quick_access_services' => ['p2p']]],
        ]);
        $second = User::factory()->create([
            'preferences' => ['dashboard' => ['quick_access_services' => ['rewards']]],
        ]);

        Sanctum::actingAs($first);
        $this->getJson('/api/me/services')
            ->assertOk()
            ->assertJsonPath('data.quick_access.services', ['p2p']);

        Sanctum::actingAs($second);
        $this->getJson('/api/me/services')
            ->assertOk()
            ->assertJsonPath('data.quick_access.services', ['rewards']);

        $this->getJson('/api/public/services')
            ->assertOk()
            ->assertJsonMissingPath('data.quick_access');
    }

    public function test_feature_flag_controls_service_availability_and_quick_access(): void
    {
        config(['exacard.production_issuance_enabled' => true]);
        $user = User::factory()->create([
            'preferences' => ['dashboard' => ['quick_access_services' => ['exacard', 'p2p']]],
        ]);
        Sanctum::actingAs($user);

        $this->getJson('/api/me/services')
            ->assertOk()
            ->assertJsonPath('data.quick_access.services', ['exacard', 'p2p'])
            ->assertJsonPath('data.services.1.available', true);
    }
}
