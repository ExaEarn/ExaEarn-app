<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\ExaPayOfflineOperationsController;
use App\Http\Controllers\ExaPayOfflineController;
use Illuminate\Support\Facades\Route;

Route::prefix('exapay/offline')->middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('capabilities', [ExaPayOfflineController::class, 'capabilities']);
    Route::get('balance', [ExaPayOfflineController::class, 'balance']);
    Route::post('devices/enroll', [ExaPayOfflineController::class, 'enroll'])->middleware('throttle:10,1,exapay-offline-enroll');
    Route::post('devices/{deviceUuid}/rotate-key', [ExaPayOfflineController::class, 'rotateDeviceKey'])->where('deviceUuid', '[A-Fa-f0-9-]{36}')->middleware('throttle:5,1,exapay-offline-rotate');
    Route::get('devices/{deviceUuid}/attestation-challenge', [ExaPayOfflineController::class, 'createAttestationChallenge'])->where('deviceUuid', '[A-Fa-f0-9-]{36}')->middleware('throttle:10,1,exapay-offline-attestation-challenge');
    Route::post('devices/{deviceUuid}/attestation', [ExaPayOfflineController::class, 'attestDevice'])->where('deviceUuid', '[A-Fa-f0-9-]{36}')->middleware('throttle:5,1,exapay-offline-attestation');
    Route::post('instruments', [ExaPayOfflineController::class, 'issue'])->middleware('throttle:10,1,exapay-offline-issue');
    Route::get('instruments/{reference}', [ExaPayOfflineController::class, 'status'])->where('reference', '[A-Za-z0-9_-]{3,80}');
    Route::post('instruments/{reference}/acknowledge', [ExaPayOfflineController::class, 'acknowledge'])->where('reference', '[A-Za-z0-9_-]{3,80}')->middleware('throttle:20,1,exapay-offline-ack');
    Route::post('instruments/{reference}/recover', [ExaPayOfflineController::class, 'recover'])->where('reference', '[A-Za-z0-9_-]{3,80}')->middleware('throttle:10,1,exapay-offline-recover');
    Route::post('journals', [ExaPayOfflineController::class, 'submitJournal'])->middleware('throttle:10,1,exapay-offline-journal');
    Route::post('journals/sync', [ExaPayOfflineController::class, 'submitJournal'])->middleware('throttle:10,1,exapay-offline-journal-sync');
    Route::post('transfers/reconcile', [ExaPayOfflineController::class, 'reconcileTransfers'])->middleware('throttle:10,1,exapay-offline-transfer-reconcile');
    Route::get('reconciliation/claims', [ExaPayOfflineController::class, 'reconciliationClaims']);
    Route::post('reconciliation/claims', [ExaPayOfflineController::class, 'createReconciliationClaim'])->middleware('throttle:10,1,exapay-offline-claim-create');
    Route::get('reconciliation/claims/{claimUuid}', [ExaPayOfflineController::class, 'reconciliationClaim'])->where('claimUuid', '[A-Fa-f0-9-]{36}');
    Route::post('reconciliation/claims/{claimUuid}/evidence', [ExaPayOfflineController::class, 'appendReconciliationEvidence'])->where('claimUuid', '[A-Fa-f0-9-]{36}')->middleware('throttle:10,1,exapay-offline-claim-evidence');
    Route::post('reconciliation/claims/{claimUuid}/redeem', [ExaPayOfflineController::class, 'redeemReconciliationClaim'])->where('claimUuid', '[A-Fa-f0-9-]{36}')->middleware('throttle:5,1,exapay-offline-claim-redeem');
    Route::get('redemptions', [ExaPayOfflineController::class, 'redemptions']);
    Route::get('redemptions/{redemptionUuid}', [ExaPayOfflineController::class, 'redemption'])->where('redemptionUuid', '[A-Fa-f0-9-]{36}');
});

Route::prefix('admin/exapay/offline')->middleware(['auth:sanctum', 'admin.security', 'admin.audit'])->group(function (): void {
    Route::get('overview', [ExaPayOfflineOperationsController::class, 'overview'])->middleware('check.permission:exapay.offline.view');
    Route::get('devices', [ExaPayOfflineOperationsController::class, 'devices'])->middleware('check.permission:exapay.offline.view');
    Route::post('devices/{device}/suspend', [ExaPayOfflineOperationsController::class, 'suspendDevice'])->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::post('devices/{device}/compromise', [ExaPayOfflineOperationsController::class, 'compromiseDevice'])->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::get('issuances', [ExaPayOfflineOperationsController::class, 'issuances'])->middleware('check.permission:exapay.offline.view');
    Route::get('transfers', [ExaPayOfflineOperationsController::class, 'transfers'])->middleware('check.permission:exapay.offline.view');
    Route::get('claims', [ExaPayOfflineOperationsController::class, 'claims'])->middleware('check.permission:exapay.offline.view');
    Route::get('redemptions', [ExaPayOfflineOperationsController::class, 'redemptions'])->middleware('check.permission:exapay.offline.view');
    Route::post('claims/{claimUuid}/retry', [ExaPayOfflineOperationsController::class, 'retryClaim'])->where('claimUuid', '[A-Fa-f0-9-]{36}')->middleware(['check.permission:exapay.offline.manage', 'rate.limit']);
    Route::post('redemptions/{redemptionUuid}/recover', [ExaPayOfflineOperationsController::class, 'recoverRedemption'])->where('redemptionUuid', '[A-Fa-f0-9-]{36}')->middleware(['check.permission:exapay.offline.manage', 'rate.limit']);
    Route::get('risk-policy', [ExaPayOfflineOperationsController::class, 'riskPolicy'])->middleware('check.permission:exapay.offline.view');
    Route::put('risk-policy', [ExaPayOfflineOperationsController::class, 'riskPolicy'])->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::get('policy-approvals', [ExaPayOfflineOperationsController::class, 'policyApprovals'])->middleware('check.permission:exapay.offline.view');
    Route::post('emergency-resume', [ExaPayOfflineOperationsController::class, 'proposeEmergencyResume'])->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::post('risk-policy/approvals/{approvalUuid}/approve', [ExaPayOfflineOperationsController::class, 'approveRiskPolicy'])->where('approvalUuid', '[A-Fa-f0-9-]{36}')->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::post('emergency-suspend', [ExaPayOfflineOperationsController::class, 'suspend'])->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::post('device-families/restrict', [ExaPayOfflineOperationsController::class, 'restrictDeviceFamily'])->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::get('exposure', [ExaPayOfflineOperationsController::class, 'exposure'])->middleware('check.permission:exapay.offline.view');
    Route::get('issuer-keys', [ExaPayOfflineOperationsController::class, 'issuerKeys'])->middleware('check.permission:exapay.offline.view');
    Route::get('fraud-cases', [ExaPayOfflineOperationsController::class, 'fraudCases'])->middleware('check.permission:exapay.offline.view');
    Route::post('fraud-cases/{caseId}/investigate', [ExaPayOfflineOperationsController::class, 'investigateFraudCase'])->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::post('issuer-keys/{keyId}/compromise', [ExaPayOfflineOperationsController::class, 'compromiseIssuerKey'])->where('keyId', '[A-Fa-f0-9]{32}')->middleware(['check.permission:exapay.offline.manage', 'recent.auth', 'rate.limit']);
    Route::get('audit-events', [ExaPayOfflineOperationsController::class, 'auditEvents'])->middleware('check.permission:exapay.offline.view');
});
