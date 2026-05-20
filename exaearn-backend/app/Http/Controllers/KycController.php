<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\Kyc\NotifyJob;
use App\Jobs\Kyc\VerifyKycJob;
use App\Models\KycVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class KycController extends Controller
{
    public function upload(Request $request): JsonResponse
    {
        $user = $request->user();

        $maxAttempts = (int) config('kyc.rules.max_attempts_per_day', 5);
        $todayAttempts = KycVerification::query()
            ->where('user_id', $user->id)
            ->whereDate('created_at', today())
            ->count();

        if ($todayAttempts >= $maxAttempts) {
            return response()->json(['message' => 'Daily KYC attempt limit reached.'], 429);
        }

        $payload = $request->validate([
            'document' => ['required', 'file', 'max:' . (int) config('kyc.rules.upload_max_kb', 5120), 'mimetypes:' . implode(',', config('kyc.rules.allowed_doc_mimes', []))],
            'selfie' => ['required', 'file', 'max:' . (int) config('kyc.rules.upload_max_kb', 5120), 'mimetypes:' . implode(',', config('kyc.rules.allowed_selfie_mimes', []))],
            'document_type' => ['required', 'string', 'max:100'],
            'level' => ['required', 'integer', 'min:1', 'max:3'],
        ]);

        $documentPath = $request->file('document')->store('kyc/documents', 'local');
        $selfiePath = $request->file('selfie')->store('kyc/selfies', 'local');

        $kyc = KycVerification::query()->create([
            'user_id' => $user->id,
            'status' => 'pending',
            'level' => (int) $payload['level'],
            'document' => $documentPath,
            'selfie' => $selfiePath,
            'document_type' => $payload['document_type'],
        ]);

        VerifyKycJob::dispatch($kyc->id);
        NotifyJob::dispatch($user->id, 'kyc.uploaded', 'KYC Uploaded', 'Your KYC documents were uploaded and are being verified.');

        return response()->json([
            'message' => 'KYC uploaded successfully and queued for verification.',
            'data' => $kyc,
        ], 202);
    }
}
