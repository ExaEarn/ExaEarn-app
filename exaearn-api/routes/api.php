<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyRewardController;
use App\Http\Controllers\GiftCardPricingController;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::get('email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])
        ->middleware('signed')
        ->name('verification.verify');
    Route::post('email/verify', [AuthController::class, 'verifyEmail'])
        ->middleware('signed');

    Route::post('2fa/enable', [AuthController::class, 'enableTwoFactor'])->middleware('dev.auth');
    Route::post('2fa/verify', [AuthController::class, 'verifyTwoFactor'])->middleware('throttle:login');

    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::post('logout', [AuthController::class, 'logout'])->middleware('dev.auth');

    // Token-authenticated user details (used by the SPA to restore session)
    Route::get('me', function (\Illuminate\Http\Request $request) {
        return response()->json([
            'status' => 'success',
            'user' => $request->user(),
        ]);
    })->middleware('dev.auth');
});

Route::middleware(['dev.auth', 'throttle:60,1'])->group(function () {
    Route::get('points', [DailyRewardController::class, 'points']);
    Route::get('checkin/history', [DailyRewardController::class, 'history']);
    Route::post('checkin', [DailyRewardController::class, 'checkin'])->middleware('throttle:6,1');
    Route::post('mystery-box/open', [DailyRewardController::class, 'openMysteryBox'])->middleware('throttle:6,1');
    Route::post('redeem', [DailyRewardController::class, 'redeem'])->middleware('throttle:3,1');
});

Route::prefix('rates')->middleware('throttle:120,1')->group(function () {
    Route::get('/', [GiftCardPricingController::class, 'show']);
    Route::post('lock', [GiftCardPricingController::class, 'lock'])->middleware('dev.auth');
    Route::get('locks/{lockId}', [GiftCardPricingController::class, 'lockStatus'])->middleware('dev.auth');
});

Route::prefix('admin/giftcard-pricing')->middleware(['dev.auth', 'throttle:60,1'])->group(function () {
    Route::get('/', [GiftCardPricingController::class, 'adminIndex']);
    Route::patch('{brand}', [GiftCardPricingController::class, 'adminUpdate']);
});
