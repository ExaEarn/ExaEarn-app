<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\ListingApplication;
use App\Models\ListingOrganization;
use App\Models\User;
use App\Services\ListingIntegrationCertificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingIntegrationCertificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_disabled_or_non_isolated_execution_fails_closed_without_synthetic_pass(): void
    {
        config(['listing.integration_certification_execution_enabled' => false]);
        $user = User::factory()->create();
        $organization = ListingOrganization::query()->create([
            'organization_uuid' => fake()->uuid(),
            'owner_user_id' => $user->id,
            'legal_name' => 'Certification Safety Ltd',
            'project_name' => 'Certification Safety',
            'jurisdiction' => 'NG',
        ]);
        $application = ListingApplication::query()->create([
            'application_uuid' => fake()->uuid(),
            'reference' => 'LIST-CERT-SAFETY',
            'organization_id' => $organization->id,
            'submitted_by' => $user->id,
            'application_type' => 'NEW_TOKEN_LISTING',
            'application_status' => 'APPROVED',
            'integration_status' => 'MARKET_CREATED',
        ]);

        $results = app(ListingIntegrationCertificationService::class)->run($application);
        $required = collect($results)->where('required', true);

        $this->assertNotEmpty($required);
        $this->assertTrue($required->every(fn (array $result): bool => $result['status'] === 'BLOCKED'));
        $this->assertFalse(collect($results)->contains('status', 'PASS'));
        $this->assertTrue(collect($results)->every(fn (array $result): bool => isset($result['correlation_id'], $result['configuration_fingerprint'], $result['started_at'], $result['completed_at'])));
    }
}
