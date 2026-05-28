# ExaEarn Notification Engine

Complete notification system for ExaEarn backend with support for in-app, email, and push notifications.

## Features

- **In-App Notifications**: Stored in database, accessible via REST API
- **Email Notifications**: Sent via Mailgun/SMTP with retry logic
- **Push Notifications**: Sent via Firebase Cloud Messaging (FCM)
- **Device Token Management**: Track and manage user devices for push notifications
- **Notification Logging**: Detailed logs for every notification event
- **Queue-Based Processing**: Redis/database queue support with exponential backoff
- **Automatic Cleanup**: Old notifications pruned automatically (configurable)
- **Retry Mechanism**: Failed notifications retried up to 3 times

## Database Tables

### `notifications`
- Main notification record
- Tracks status, read state, delivery channels
- Stores notification data as JSON

### `device_tokens`
- Firebase/FCM tokens for push notifications
- Associated with user account
- Tracks device type (iOS, Android, Web)
- Supports activation/deactivation

### `notification_logs`
- Audit trail for all notification events
- Stores provider responses and errors
- Enables troubleshooting failed deliveries

## Models

### `Notification`
```php
$notification = Notification::create([
    'user_id' => $user->id,
    'type' => 'withdrawal_success',
    'title' => 'Withdrawal Successful',
    'message' => 'Your withdrawal has been processed',
    'data' => ['amount' => '100', 'currency' => 'USD'],
    'channel' => 'in_app',
    'status' => 'pending',
]);

$notification->markAsRead();
$notification->markAsSent();
$notification->markAsFailed('Error message');
```

### `DeviceToken`
```php
DeviceToken::create([
    'user_id' => $user->id,
    'token' => 'fcm_token_...',
    'device_type' => 'ios',
    'device_name' => 'iPhone 14',
]);

$deviceToken->activate();
$deviceToken->deactivate();
$deviceToken->updateLastUsed();
```

## NotificationService

Core service for creating and managing notifications.

```php
$notificationService = app(NotificationService::class);

// Create notification with automatic channel routing
$notification = $notificationService->create(
    user: $user,
    type: 'deposit_confirmed',
    title: 'Deposit Confirmed',
    message: 'Your deposit has been confirmed',
    channels: ['in_app', 'email', 'push'],
    data: ['amount' => '100', 'txid' => '0x123...']
);

// Specialized notification methods
$notificationService->notifyWithdrawalSuccess($user, [
    'amount' => '100',
    'currency' => 'USD',
    'transaction_id' => 'TXN123',
]);

$notificationService->notifyDepositConfirmed($user, [
    'amount' => '100',
    'currency' => 'USD',
    'transaction_id' => 'TXN456',
]);

$notificationService->notifyRewardEarned($user, [
    'amount' => '50',
    'currency' => 'EXA',
    'type' => 'referral_bonus',
]);

// Retrieve notifications
$unread = $notificationService->getUnreadNotifications($user);
$paginated = $notificationService->getPaginatedNotifications($user, perPage: 20);
$stats = $notificationService->getNotificationStats($user);

// Mark operations
$notificationService->markAllAsRead($user);

// Maintenance
$notificationService->retryFailedNotifications(maxRetries: 3);
$notificationService->cleanupOldNotifications(daysOld: 90);
```

## Queue Jobs

### `SendEmailNotificationJob`
- Sends email via Mailgun/SMTP
- Retry logic: 3 attempts with exponential backoff (60s, 300s, 900s)
- Dequeued when email config is enabled

### `SendPushNotificationJob`
- Sends push via Firebase Cloud Messaging
- Deactivates invalid device tokens automatically
- Retry logic: 3 attempts with exponential backoff
- Tracks devices successfully sent to

## API Endpoints

All endpoints require authentication (`auth:sanctum`).

### Get Notifications
```
GET /api/notifications
GET /api/notifications?per_page=20&page=1
```

Response:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "type": "withdrawal_success",
      "title": "Withdrawal Successful",
      "message": "Your withdrawal...",
      "status": "read",
      "read_at": "2024-01-15T10:30:00Z",
      "created_at": "2024-01-15T10:25:00Z"
    }
  ],
  "pagination": {...}
}
```

### Get Unread Notifications
```
GET /api/notifications/unread
```

### Get Notification Stats
```
GET /api/notifications/stats
```

Response:
```json
{
  "success": true,
  "data": {
    "total": 42,
    "unread": 5,
    "read": 35,
    "failed": 2
  }
}
```

### Get Single Notification
```
GET /api/notifications/{id}
```

### Mark as Read
```
PUT /api/notifications/{id}/read
POST /api/notifications/mark-all-read
```

### Delete Notification
```
DELETE /api/notifications/{id}
DELETE /api/notifications (delete all)
```

### Device Tokens
```
POST /api/notifications/device-tokens
GET /api/notifications/device-tokens
DELETE /api/notifications/device-tokens/{tokenId}
POST /api/notifications/device-tokens/deactivate-all
```

Register device token:
```json
{
  "token": "fcm_token_...",
  "device_type": "ios|android|web",
  "device_name": "iPhone 14"
}
```

## Configuration

### `config/notifications.php`

```php
// Default channels for all notifications
'default_channels' => ['in_app', 'email', 'push']

// Notification retention period
'retention_days' => 90

// Max retry attempts
'max_retries' => 3

// Email configuration
'email' => [
    'enabled' => true,
    'from' => 'noreply@exaearn.com',
]

// Push notification configuration
'push' => [
    'enabled' => true,
    'provider' => 'firebase',
]

// Per-type channel configuration
'types' => [
    'withdrawal_success' => ['in_app', 'email', 'push'],
    'deposit_confirmed' => ['in_app', 'email', 'push'],
    // ...
]

// Queue configuration
'queue' => [
    'connection' => 'redis',
    'name' => 'notifications',
    'timeout' => 60,
]

// Cleanup schedule
'cleanup' => [
    'enabled' => true,
    'retention_days' => 90,
    'schedule' => '0 2 * * *', // 2 AM daily
]
```

## Environment Variables

```bash
# Email
MAIL_FROM_ADDRESS=noreply@exaearn.com
MAIL_FROM_NAME="ExaEarn"
MAILGUN_DOMAIN=mg.exaearn.com
MAILGUN_SECRET=your_mailgun_api_key

# Firebase/FCM
FIREBASE_API_KEY=your_firebase_api_key
FIREBASE_PROJECT_ID=your_firebase_project_id

# Queue
QUEUE_CONNECTION=redis
REDIS_QUEUE_CONNECTION=default
REDIS_QUEUE=notifications
```

## Integration with Fiat Withdrawal

When a fiat withdrawal is completed:

```php
// In FiatWithdrawalService or webhook handler
$notificationService->notifyWithdrawalSuccess($user, [
    'amount' => $withdrawal->amount,
    'currency' => $withdrawal->currency,
    'transaction_id' => $withdrawal->transaction_id,
    'bank_account' => $withdrawal->account_number,
    'estimated_arrival' => $withdrawal->estimated_arrival,
]);
```

## Authorization

- Users can only view/manage their own notifications
- Policies enforced via `NotificationPolicy` and `DeviceTokenPolicy`
- Admin users can access notification logs if needed

## Testing

### Manual Test via Artisan Tinker

```php
// Create test user notification
$user = User::first();
$notificationService = app(\App\Services\NotificationService::class);

$notification = $notificationService->notifyWithdrawalSuccess($user, [
    'amount' => '100',
    'currency' => 'USD',
    'transaction_id' => 'TEST123',
]);

// Register device token
$user->deviceTokens()->create([
    'token' => 'test_fcm_token',
    'device_type' => 'web',
    'device_name' => 'Test Browser',
]);

// Process queue jobs
\Illuminate\Support\Facades\Queue::fake(); // or actual processing
```

### API Test

```bash
# Get unread notifications
curl -H "Authorization: Bearer {token}" \
  http://localhost:8000/api/notifications/unread

# Register device
curl -X POST -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"token":"fcm_...","device_type":"ios"}' \
  http://localhost:8000/api/notifications/device-tokens
```

## Troubleshooting

### Push notifications not sending
1. Verify `FIREBASE_API_KEY` and `FIREBASE_PROJECT_ID` in `.env`
2. Check device tokens are active: `DeviceToken::where('is_active', true)->count()`
3. Review logs: `NotificationLog::where('event', 'failed')->latest()->get()`

### Email not sending
1. Verify Mailgun/SMTP credentials in `.env`
2. Check `MAIL_FROM_ADDRESS` is set
3. Review queue status: `php artisan queue:failed`

### Queue jobs not processing
1. Start queue worker: `php artisan queue:work`
2. Or for async: use `--daemon` flag
3. Check queue config in `config/queue.php`

## Performance Considerations

- Notifications are queued and processed asynchronously
- Device tokens are deactivated automatically on failed deliveries
- Old notifications pruned daily via scheduler
- Indexes on user_id, status, and created_at for fast queries
- Supports batching of push notifications to multiple devices
