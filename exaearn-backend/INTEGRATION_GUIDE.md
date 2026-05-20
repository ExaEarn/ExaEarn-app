# Notification Engine Integration Guide

This guide shows how to integrate the new notification engine with existing ExaEarn systems.

## Integration with Fiat Withdrawal System

### Option 1: In FiatWithdrawalService

Update the `handleFlutterwaveWebhook()` and `handleNombaWebhook()` methods to notify users:

```php
// In app/Services/FiatWithdrawalService.php

use App\Services\NotificationService;

public function __construct(
    // ... existing dependencies
    private NotificationService $notificationService,
) {
}

public function handleFlutterwaveWebhook(array $payload): void
{
    $withdrawal = $this->getWithdrawalByReference($payload['data']['tx_ref']);
    
    if ($payload['data']['status'] === 'successful') {
        // Update withdrawal status
        $withdrawal->update([
            'status' => 'completed',
            'provider_reference' => $payload['data']['id'],
            'completed_at' => now(),
        ]);

        // Send notification to user
        $this->notificationService->notifyWithdrawalSuccess($withdrawal->user, [
            'amount' => $withdrawal->amount,
            'currency' => $withdrawal->currency,
            'transaction_id' => $payload['data']['id'],
            'bank_name' => $payload['data']['customer']['name'],
            'estimated_arrival' => now()->addHours(24)->toIso8601String(),
        ]);
    } elseif ($payload['data']['status'] === 'failed') {
        $withdrawal->update(['status' => 'failed']);
        
        $this->notificationService->notifySystemAlert(
            $withdrawal->user,
            'Withdrawal Failed',
            'Your withdrawal request could not be processed. Please try again or contact support.',
            ['withdrawal_id' => $withdrawal->id, 'reference' => $payload['data']['tx_ref']]
        );
    }
}

public function handleNombaWebhook(array $payload): void
{
    $withdrawal = $this->getWithdrawalByReference($payload['reference']);
    
    if ($payload['status'] === 'success') {
        $withdrawal->update([
            'status' => 'completed',
            'provider_reference' => $payload['transactionId'],
            'completed_at' => now(),
        ]);

        $this->notificationService->notifyWithdrawalSuccess($withdrawal->user, [
            'amount' => $withdrawal->amount,
            'currency' => $withdrawal->currency,
            'transaction_id' => $payload['transactionId'],
            'account_number' => $payload['destinationAccountNumber'],
        ]);
    }
}
```

### Option 2: In WebhookController

Update the webhook handlers in the controller:

```php
// In app/Http/Controllers/WebhookController.php

use App\Services\NotificationService;

public function __construct(
    private FiatWithdrawalService $fiatWithdrawalService,
    private NotificationService $notificationService,
) {
}

public function flutterwaveWithdrawal(Request $request)
{
    $payload = $request->all();
    
    // Process withdrawal
    $result = $this->fiatWithdrawalService->handleFlutterwaveWebhook($payload);
    
    // Notification is sent inside the service
    
    return response()->json(['success' => true]);
}

public function nombaWithdrawal(Request $request)
{
    $payload = $request->all();
    
    // Process withdrawal
    $result = $this->fiatWithdrawalService->handleNombaWebhook($payload);
    
    // Notification is sent inside the service
    
    return response()->json(['success' => true]);
}
```

## Integration with Deposit System

Update deposit webhook handlers:

```php
// In WebhookController or DepositService

public function deposit(Request $request)
{
    $payload = $request->validate([
        'user_id' => 'required|integer',
        'amount' => 'required|numeric',
        'currency' => 'required|string',
        'transaction_id' => 'required|string',
        'status' => 'required|in:confirmed,pending,failed',
    ]);

    $user = User::findOrFail($payload['user_id']);
    
    if ($payload['status'] === 'confirmed') {
        // Update wallet/balance
        
        // Send notification
        $notificationService->notifyDepositConfirmed($user, [
            'amount' => $payload['amount'],
            'currency' => $payload['currency'],
            'transaction_id' => $payload['transaction_id'],
            'confirmed_at' => now()->toIso8601String(),
        ]);
    } elseif ($payload['status'] === 'failed') {
        $notificationService->notifySystemAlert(
            $user,
            'Deposit Failed',
            "Your deposit of {$payload['amount']} {$payload['currency']} could not be processed.",
            ['transaction_id' => $payload['transaction_id']]
        );
    }
}
```

## Integration with Reward System

Update reward distribution:

```php
// In RewardService or DistributeRewardJob

use App\Services\NotificationService;

public function distributeReward(User $user, RewardActivity $activity, float $amount)
{
    // Calculate and store reward
    $reward = $user->rewards()->create([
        'activity_id' => $activity->id,
        'amount' => $amount,
        'currency' => 'EXA',
        'status' => 'earned',
    ]);

    // Send notification
    app(NotificationService::class)->notifyRewardEarned($user, [
        'amount' => $amount,
        'currency' => 'EXA',
        'type' => $activity->type,
        'activity_name' => $activity->name,
        'total_balance' => $user->getEXABalance(),
    ]);
}
```

## Integration with Trading System

Send alerts for price movements or position changes:

```php
// In FuturesService or TradeService

public function monitorPositions()
{
    $positions = FuturesPosition::active()->with('user')->get();
    
    foreach ($positions as $position) {
        $profitLoss = $position->calculatePnL();
        $pnlPercent = ($profitLoss / $position->entry_value) * 100;
        
        if ($pnlPercent >= 50 || $pnlPercent <= -20) {
            $this->notificationService->notifyTradingAlert(
                $position->user,
                'Position Alert: ' . strtoupper($position->symbol),
                "Your {$position->symbol} position is {$pnlPercent}% PnL. Entry: {$position->entry_price}, Current: {$position->current_price}",
                [
                    'symbol' => $position->symbol,
                    'pnl_percent' => $pnlPercent,
                    'entry_price' => $position->entry_price,
                    'current_price' => $position->current_price,
                ]
            );
        }
    }
}
```

## Integration with Security Events

Send alerts for account security events:

```php
// In AuthController or Security middleware

public function logSuspiciousActivity(User $user, string $reason, array $context = [])
{
    // Log suspicious activity
    $fraud = FraudLog::create([
        'user_id' => $user->id,
        'reason' => $reason,
        'context' => $context,
    ]);

    // Send security alert
    $notificationService->notifySystemAlert(
        $user,
        'Security Alert',
        "Suspicious activity detected on your account: {$reason}. If this wasn't you, please secure your account immediately.",
        ['fraud_log_id' => $fraud->id]
    );
}

public function login(Request $request)
{
    $validated = $request->validate([...]);
    
    if (auth()->attempt($validated)) {
        $user = auth()->user();
        
        // Check for new device/location
        $deviceId = $this->getDeviceFingerprint();
        $lastLogin = $user->logins()->latest()->first();
        
        if (!$lastLogin || $lastLogin->device_id !== $deviceId) {
            $notificationService->notifySystemAlert(
                $user,
                'New Login',
                "Your account was accessed from a new device/location: {$this->getLocationName()}",
                [
                    'device_id' => $deviceId,
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]
            );
        }
        
        return response()->json(['token' => $user->createToken('api')->plainTextToken]);
    }
}
```

## Device Token Registration Flow

Frontend should register device token on app startup:

```javascript
// In React frontend (exaearn-frontend)

useEffect(() => {
    // Request FCM permission and get token
    if ('Notification' in window) {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                // Get FCM token (using Firebase SDK)
                getMessagingToken().then(token => {
                    // Register with backend
                    fetch('/api/notifications/device-tokens', {
                        method: 'POST',
                        headers: {
                            'Authorization': `Bearer ${authToken}`,
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            token: token,
                            device_type: getDeviceType(), // 'ios', 'android', 'web'
                            device_name: getDeviceName(),
                        }),
                    });
                });
            }
        });
    }
}, [authToken]);
```

## Queue Worker Configuration

Ensure queue worker is running:

```bash
# For development
php artisan queue:work --queue=notifications

# For production (with supervisor)
# See exaearn-backend supervisor configuration files
```

## Testing the Integration

```php
// In tinker or test
$user = User::first();
$notificationService = app(App\Services\NotificationService::class);

// Test withdrawal notification
$notificationService->notifyWithdrawalSuccess($user, [
    'amount' => '100',
    'currency' => 'USD',
    'transaction_id' => 'TEST123',
]);

// Test deposit notification
$notificationService->notifyDepositConfirmed($user, [
    'amount' => '500',
    'currency' => 'USDT',
    'transaction_id' => 'DEPOSIT123',
]);

// Check notifications
$user->notifications()->latest()->limit(5)->get();
```

## Monitoring & Troubleshooting

### Check notification status
```php
// Get failed notifications
Notification::where('status', 'failed')->get();

// Retry failed
$notificationService->retryFailedNotifications(3);

// Get logs
NotificationLog::where('event', 'failed')->latest()->limit(10)->get();
```

### Queue monitoring
```bash
php artisan queue:failed       # View failed jobs
php artisan queue:retry all    # Retry all failed jobs
php artisan queue:flush        # Clear all jobs
```

## Performance Notes

- Notifications are queued immediately (non-blocking)
- Email sending happens async via queue worker
- Push notifications deactivate invalid tokens automatically
- Old notifications cleaned up daily (configurable)
- All queries indexed for performance

## Security Considerations

- Users can only access their own notifications
- Authorization enforced via policies
- Device tokens validated before use
- Sensitive data stored securely in encrypted fields
- Notification data can contain sensitive info (handle carefully)
