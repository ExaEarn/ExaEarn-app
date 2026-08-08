<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_retrieve_identity_and_avatar_library(): void
    {
        $user = User::factory()->create([
            'name' => 'Ada Lovelace',
            'unique_user_id' => 'EXA-TESTUSER',
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile/identity')
            ->assertOk()
            ->assertJsonPath('data.identity.display_type', 'initials')
            ->assertJsonPath('data.identity.initials', 'AL')
            ->assertJsonPath('data.user.verification.kyc_level', 0);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/profile/avatars')
            ->assertOk()
            ->assertJsonStructure(['data' => [['category', 'avatars']]]);
    }

    public function test_user_can_select_avatar_and_privacy_without_affecting_kyc(): void
    {
        $user = User::factory()->create([
            'kyc_level' => 2,
            'kyc_verified_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/avatar', [
                'avatar_id' => 'exaearn-prime',
                'visibility' => 'p2p',
            ])
            ->assertOk()
            ->assertJsonPath('data.avatar_id', 'exaearn-prime')
            ->assertJsonPath('data.profile_display_type', 'avatar')
            ->assertJsonPath('data.profile_visibility', 'p2p')
            ->assertJsonPath('data.verification.kyc_level', 2);

        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'type' => 'profile',
            'action' => 'avatar_selected',
            'status' => 'success',
        ]);
    }

    public function test_user_can_switch_back_to_initials(): void
    {
        $user = User::factory()->create([
            'avatar_id' => 'classic-gold',
            'profile_display_type' => 'avatar',
            'profile_visibility' => 'public',
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/profile/initials', ['visibility' => 'self'])
            ->assertOk()
            ->assertJsonPath('data.profile_display_type', 'initials')
            ->assertJsonPath('data.profile_visibility', 'self');
    }
    public function test_user_can_upload_optimized_webp_profile_image_when_gd_is_enabled(): void
    {
        if (!\function_exists('imagewebp')) {
            $this->markTestSkipped('GD WebP support is not enabled for this PHP runtime.');
        }

        Storage::fake('local');
        $user = User::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->post('/api/profile/image', [
                'image' => UploadedFile::fake()->image('profile.jpg', 640, 480)->size(512),
                'visibility' => 'public',
            ])
            ->assertCreated()
            ->assertJsonPath('data.profile_display_type', 'custom_image')
            ->assertJsonPath('data.profile_image_status', 'approved')
            ->assertJsonPath('data.profile_visibility', 'public');

        $user->refresh();
        $this->assertNotNull($user->profile_image_url);
        $this->assertNotNull($user->profile_thumbnail_url);
        $this->assertStringEndsWith('.webp', $user->profile_image_url);
        Storage::disk('local')->assertExists($user->profile_image_url);
        Storage::disk('local')->assertExists($user->profile_thumbnail_url);
    }
}