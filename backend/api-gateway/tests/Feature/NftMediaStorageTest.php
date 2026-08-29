<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Nft;
use App\Models\NftMediaAsset;
use App\Models\NftReport;
use App\Models\User;
use App\Services\NftMediaService;
use App\Services\NftService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class NftMediaStorageTest extends TestCase
{
    use RefreshDatabase;

    private function tinyPng(string $name = 'art.png'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent(
            $name,
            base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII=')
        );
    }

    public function test_valid_image_upload_creates_hash_metadata_and_updates_nft_reference(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $nft = app(NftService::class)->mint($user, ['utility_type' => 'creator_access', 'name' => 'Media NFT', 'wallet_address' => '0xuser', 'collection_name' => 'Media']);

        $response = $this->actingAs($user)->postJson('/api/nft/media', [
            'file' => $this->tinyPng(),
            'nft_id' => $nft->id,
            'media_type' => 'IMAGE',
            'visibility' => 'PUBLIC',
            'name' => 'Media NFT',
        ])->assertCreated()->json('data');

        $this->assertSame('READY', $response['status']);
        $this->assertSame('READY', $response['processing_status']);
        $this->assertNotEmpty($response['checksum']);
        $this->assertNotEmpty($response['metadata_hash']);
        $this->assertSame($response['public_uri'], Nft::query()->findOrFail($nft->id)->media_url);
    }

    public function test_executable_or_script_upload_is_rejected(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/nft/media', [
            'file' => UploadedFile::fake()->create('payload.php', 1, 'application/x-php'),
            'media_type' => 'IMAGE',
            'visibility' => 'PUBLIC',
        ])->assertStatus(422)->assertJsonPath('message', 'Unsupported NFT media MIME type.');
    }

    public function test_duplicate_content_reuses_existing_ready_media_object(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();
        $file = $this->tinyPng('same.png');

        $first = app(NftMediaService::class)->upload($user, $file, ['media_type' => 'IMAGE', 'visibility' => 'PUBLIC']);
        $second = app(NftMediaService::class)->upload($user, $file, ['media_type' => 'IMAGE', 'visibility' => 'PUBLIC']);

        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('nft_media_assets', 1);
    }

    public function test_private_report_evidence_is_not_public_and_is_owner_scoped(): void
    {
        Storage::fake('local');
        $seller = User::factory()->create();
        $reporter = User::factory()->create();
        $other = User::factory()->create();
        $nft = app(NftService::class)->mint($seller, ['utility_type' => 'creator_access', 'name' => 'Evidence NFT', 'wallet_address' => '0xseller', 'collection_name' => 'Evidence']);
        $report = NftReport::query()->create(['nft_id' => $nft->id, 'reported_by_user_id' => $reporter->id, 'report_type' => 'COPYRIGHT_IP', 'status' => 'OPEN']);

        $asset = app(NftMediaService::class)->uploadEvidence($reporter, $report, UploadedFile::fake()->create('proof.pdf', 8, 'application/pdf'));

        $this->assertSame('PRIVATE', $asset->visibility);
        $this->assertNull($asset->public_uri);
        $this->actingAs($other)->getJson("/api/nft/media/{$asset->id}/private-url")->assertForbidden();
        $this->actingAs($reporter)->getJson("/api/nft/media/{$asset->id}/private-url")->assertOk();
    }

    public function test_production_storage_fails_closed_without_provider_configuration(): void
    {
        Config::set('nft.media.mode', 'PRODUCTION');
        Config::set('nft.media.production_configured', false);
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/nft/media', [
            'file' => $this->tinyPng(),
            'media_type' => 'IMAGE',
            'visibility' => 'PUBLIC',
        ])->assertStatus(422)->assertJsonPath('message', 'MEDIA_PROVIDER_UNAVAILABLE');
    }

    public function test_media_reconciliation_detects_missing_objects_and_private_public_leaks(): void
    {
        $user = User::factory()->create();
        NftMediaAsset::query()->create([
            'owner_user_id' => $user->id,
            'media_type' => 'IMAGE',
            'visibility' => 'PUBLIC',
            'storage_provider' => 'local',
            'storage_key' => 'nft/public/image/missing.png',
            'safe_filename' => 'missing.png',
            'mime_type' => 'image/png',
            'checksum' => hash('sha256', 'missing'),
            'content_hash' => hash('sha256', 'missing'),
            'status' => 'READY',
            'processing_status' => 'READY',
        ]);
        NftMediaAsset::query()->create([
            'owner_user_id' => $user->id,
            'media_type' => 'DOCUMENT',
            'visibility' => 'PRIVATE',
            'storage_provider' => 'local',
            'storage_key' => 'nft/private/document/leaked.pdf',
            'safe_filename' => 'leaked.pdf',
            'mime_type' => 'application/pdf',
            'checksum' => hash('sha256', 'leaked'),
            'content_hash' => hash('sha256', 'leaked'),
            'status' => 'READY',
            'processing_status' => 'READY',
            'public_uri' => 'http://example.com/leaked.pdf',
        ]);

        $result = app(NftMediaService::class)->reconciliation();

        $this->assertSame('FAIL', $result['status']);
        $this->assertDatabaseHas('nft_reconciliation_breaks', ['break_type' => 'media_missing_object', 'status' => 'OPEN']);
        $this->assertDatabaseHas('nft_reconciliation_breaks', ['break_type' => 'private_media_public_uri', 'status' => 'OPEN']);
    }
}
