<?php

use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware('auth')->prefix('admin/logs')->group(function (): void {
    Route::get('activity', [ActivityLogController::class, 'allLogs']);
    Route::get('user/{userId}', [ActivityLogController::class, 'userLogs']);
    Route::get('suspicious', [ActivityLogController::class, 'suspiciousActivity']);
    Route::get('ip-activity', [ActivityLogController::class, 'ipActivity']);
});
