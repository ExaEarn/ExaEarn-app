<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use App\Services\KycProviderService;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('kyc:sandbox-check', function (KycProviderService $provider) {
    $this->info('Running KYC provider sandbox check...');

    $payload = [
        'document' => 'sandbox-document',
        'document_type' => 'passport',
        'selfie' => 'sandbox-selfie',
        'country' => 'NG',
    ];

    try {
        $doc = $provider->verifyDocument($payload);
        $face = $provider->verifyFace($payload);
        $dup = $provider->checkDuplicate($payload);
        $country = $provider->checkCountry($payload);
    } catch (\Throwable $e) {
        $this->error('KYC sandbox check failed: ' . $e->getMessage());
        return self::FAILURE;
    }

    $this->line('verifyDocument: ' . json_encode($doc, JSON_UNESCAPED_SLASHES));
    $this->line('verifyFace: ' . json_encode($face, JSON_UNESCAPED_SLASHES));
    $this->line('checkDuplicate: ' . json_encode($dup, JSON_UNESCAPED_SLASHES));
    $this->line('checkCountry: ' . json_encode($country, JSON_UNESCAPED_SLASHES));
    $this->info('KYC sandbox check completed.');

    return self::SUCCESS;
})->purpose('Run sandbox verification against configured KYC provider/fallback');
