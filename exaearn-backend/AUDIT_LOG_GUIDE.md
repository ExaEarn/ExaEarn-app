# ExaEarn Audit & Activity Log System

## Overview

The ExaEarn Audit & Activity Log System is a production-grade logging engine that tracks all user and admin actions for security, fraud detection, compliance, and operational transparency.

**Status**: ✅ Production Ready  
**Database**: PostgreSQL (`activity_logs` table)  
**API Endpoints**: Fully RESTful with pagination & filtering

---

## Architecture

### Core Components

1. **Database Layer** (`activity_logs` table)
   - Immutable log records (no edits/deletes)
   - Optimized indexes on `user_id`, `type`, `action`, `created_at`
   - JSON `data` field for flexible context storage
   - Fields: `id`, `user_id`, `admin_id`, `type`, `action`, `ip`, `device`, `data`, `status`, `created_at`

2. **Service Layer**
   - `ActivityAuditService` - Main logging service with type-specific methods
   - `AuditService` - Legacy compatibility service
   - `AdminAuditService` - Admin-specific actions

3. **Middleware Layer**
   - `LogUserActivity` - Auto-logs API calls by type (wallet, trade, reward, etc.)
   - `AdminActionAuditMiddleware` - Captures all admin actions

4. **API Layer**
   - `ActivityLogController` - User and admin endpoints
   - RESTful routes with full filtering & pagination

---

## Data Model

### Activity Log Fields

```json
{
  "id": 1,
  "user_id": 123,                    // User performing action (null for system/admin)
  "admin_id": 5,                     // Admin performing action (null for user actions)
  "type": "auth|wallet|trade|reward|staking|nft|admin|security|system",
  "action": "login",                 // Specific action within type
  "ip": "192.168.1.1",               // Request IP address (IPv4 or IPv6)
  "device": "Mozilla/5.0...",        // User agent string
  "data": {                          // JSON context data
    "amount": 100,
    "asset": "USDT",
    "pair": "BTC/USD"
  },
  "status": "success|failed|pending",
  "created_at": "2026-05-09T10:30:00Z"
}
```

### Log Types

| Type | Purpose | Actions |
|------|---------|---------|
| `auth` | Authentication events | `login`, `logout`, `login_failed`, `register` |
| `wallet` | Wallet operations | `deposit`, `withdrawal`, `transfer`, `address_change` |
| `trade` | Trading operations | `order_created`, `order_filled`, `order_cancelled`, `liquidation` |
| `reward` | Reward distribution | `checkin_reward`, `mission_reward`, `referral_reward`, `staking_reward` |
| `staking` | Staking operations | `stake`, `unstake`, `claim_reward`, `compound` |
| `nft` | NFT operations | `mint`, `buy`, `sell`, `transfer`, `stake`, `list`, `delist` |
| `security` | Account security | `password_changed`, `email_changed`, `2fa_enabled`, `2fa_disabled` |
| `admin` | Admin actions | `adjust_balance`, `ban_user`, `approve_withdrawal`, `edit_reward` |
| `system` | System events | `migration`, `backup`, `settings_change` |

---

## Service Usage

### Basic Logging

```php
use App\Services\ActivityAuditService;

class YourController extends Controller
{
    public function someAction(Request $request, ActivityAuditService $auditService)
    {
        $user = $request->user();

        // Log user activity
        $auditService->logAuth($user->id, 'login', [
            'ip' => $request->ip(),
            'device' => $request->userAgent(),
        ]);

        // Log wallet activity
        $auditService->logWallet($user->id, 'deposit', [
            'amount' => 100,
            'asset' => 'USDT',
            'txid' => 'abc123',
        ]);

        // Log trade activity
        $auditService->logTrade($user->id, 'order_created', [
            'pair' => 'BTC/USD',
            'side' => 'buy',
            'amount' => 0.5,
            'price' => 50000,
        ]);
    }
}
```

### Admin Logging

```php
public function freezeUser($userId, AdminAuditService $adminAuditService)
{
    $admin = auth()->user();

    $adminAuditService->logAdmin(
        $admin->id,
        'user_frozen',
        ['user_id' => $userId, 'reason' => 'suspicious_activity']
    );
}
```

### System Logging

```php
$auditService->logSystem('database_migration_started', [
    'migration' => '2026_05_09_000001_create_activity_logs_table',
    'duration_ms' => 1250,
]);
```

---

## Middleware

### LogUserActivity

Automatically logs API requests by route type.

**Registered in**: `bootstrap/app.php`

```php
Route::middleware(['auth:sanctum', 'log.activity'])->group(function () {
    // All routes here auto-logged
});
```

**Auto-logs**:
- `/api/wallet/*` → wallet logs
- `/api/trade/*` → trade logs  
- `/api/reward/*` → reward logs
- `/api/staking/*` → staking logs
- `/api/nft/*` → nft logs

### AdminActionAuditMiddleware

Logs all admin API calls.

**Registered in**: `bootstrap/app.php`

```php
Route::prefix('admin')->middleware('admin.audit')->group(function () {
    // All admin actions auto-logged
});
```

---

## API Endpoints

### User Endpoints (Authenticated Users)

#### Get My Activity Logs
```http
GET /api/logs/my-activity?page=1&per_page=20&type=auth&status=success
Authorization: Bearer {token}
```

**Query Parameters**:
- `page` - Page number (default: 1)
- `per_page` - Items per page (default: 20, max: 100)
- `type` - Filter by type (auth, wallet, trade, etc.)
- `action` - Filter by action name
- `status` - Filter by status (success, failed, pending)
- `from_date` - Start date (YYYY-MM-DD)
- `to_date` - End date (YYYY-MM-DD)

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "user_id": 123,
      "type": "auth",
      "action": "login",
      "ip": "192.168.1.1",
      "device": "Mozilla/5.0...",
      "data": null,
      "status": "success",
      "created_at": "2026-05-09T10:30:00Z"
    }
  ],
  "pagination": {
    "total": 150,
    "count": 20,
    "per_page": 20,
    "current_page": 1,
    "last_page": 8,
    "from": 1,
    "to": 20,
    "has_more": true
  }
}
```

#### Get Single Activity Log
```http
GET /api/logs/activity/{id}
Authorization: Bearer {token}
```

#### Get Activity Summary
```http
GET /api/logs/summary
Authorization: Bearer {token}
```

**Response**:
```json
{
  "success": true,
  "data": {
    "total": 500,
    "by_type": {
      "auth": 45,
      "wallet": 120,
      "trade": 200,
      "reward": 135
    },
    "by_status": {
      "success": 490,
      "failed": 10
    },
    "recent_7_days": 87,
    "recent_30_days": 450
  }
}
```

---

### Admin Endpoints (Admin Users Only)

#### Get All Activity Logs
```http
GET /admin/logs/activity?page=1&per_page=20&user_id=123&type=wallet
Authorization: Bearer {token}
```

**Filters**: `user_id`, `admin_id`, `type`, `action`, `status`, `from_date`, `to_date`, `ip`

#### Get User's Activity Logs
```http
GET /admin/logs/user/{userId}?page=1&per_page=20
Authorization: Bearer {token}
```

#### Get Admin Actions Log
```http
GET /admin/logs/admin-actions?page=1&admin_id=5
Authorization: Bearer {token}
```

#### Get Suspicious Activity Report
```http
GET /admin/logs/suspicious?days=7
Authorization: Bearer {token}
```

**Response**:
```json
{
  "success": true,
  "data": [...],
  "user_risk_summary": [
    {
      "user_id": 45,
      "failed_count": 12,
      "last_incident": "2026-05-09T09:15:00Z"
    }
  ],
  "pagination": {...}
}
```

#### Get IP Activity Report
```http
GET /admin/logs/ip-activity?ip=192.168.1.1&days=7
Authorization: Bearer {token}
```

#### Export Activity Logs
```http
GET /admin/logs/export?user_id=123&type=wallet&format=json
Authorization: Bearer {token}
```

---

## Logging Points

### Authentication Events

```php
// Register
$auditService->logAuth($user->id, 'register', [
    'referral_code' => 'ref123',
    'email' => $user->email,
], 'success');

// Login Success
$auditService->logAuth($user->id, 'login', [], 'success');

// Login Failed
$auditService->logAuth($user->id, 'login_failed', [
    'email' => 'user@example.com',
], 'failed');

// Logout
$auditService->logAuth($user->id, 'logout', []);

// Password Changed
$auditService->logSecurity($user->id, 'password_changed', [
    'email' => $user->email,
]);

// Email Changed
$auditService->logSecurity($user->id, 'email_changed', [
    'old_email' => 'old@example.com',
    'new_email' => 'new@example.com',
]);

// 2FA Enabled
$auditService->logSecurity($user->id, '2fa_enabled');

// 2FA Disabled
$auditService->logSecurity($user->id, '2fa_disabled');
```

### Wallet Events

```php
// Deposit Initiated
$auditService->logWallet($user->id, 'deposit_initiated', [
    'amount' => 100,
    'asset' => 'USDT',
    'network' => 'TRC20',
    'address' => 'addr123',
]);

// Withdrawal Requested
$auditService->logWallet($user->id, 'withdrawal_requested', [
    'amount' => 50,
    'asset' => 'USDT',
    'fee' => 1,
    'address' => 'addr123',
]);

// Withdrawal Approved (Admin)
$auditService->logAdmin($admin->id, 'withdrawal_approved', [
    'user_id' => $user->id,
    'withdrawal_id' => 123,
    'amount' => 50,
]);

// Transfer
$auditService->logWallet($user->id, 'transfer', [
    'recipient_id' => 456,
    'amount' => 25,
    'asset' => 'USDT',
]);
```

### Trading Events

```php
// Order Created
$auditService->logTrade($user->id, 'order_created', [
    'pair' => 'BTC/USD',
    'side' => 'buy',
    'amount' => 0.5,
    'price' => 50000,
    'order_id' => 'ord123',
]);

// Order Filled
$auditService->logTrade($user->id, 'order_filled', [
    'order_id' => 'ord123',
    'fill_price' => 50050,
    'fill_amount' => 0.5,
]);

// Order Cancelled
$auditService->logTrade($user->id, 'order_cancelled', [
    'order_id' => 'ord123',
]);

// Liquidation
$auditService->logTrade($user->id, 'liquidation', [
    'margin_account' => 456,
    'liquidation_price' => 45000,
    'loss_amount' => 2500,
]);
```

### Reward Events

```php
// Check-in Reward
$auditService->logReward($user->id, 'checkin_reward', [
    'amount' => 10,
    'asset' => 'EXA',
]);

// Mission Reward
$auditService->logReward($user->id, 'mission_reward', [
    'mission_id' => 789,
    'amount' => 50,
    'asset' => 'EXA',
]);

// Referral Reward
$auditService->logReward($user->id, 'referral_reward', [
    'referred_user_id' => 555,
    'amount' => 100,
    'asset' => 'USD',
]);

// Staking Reward
$auditService->logReward($user->id, 'staking_reward', [
    'stake_id' => 123,
    'amount' => 50,
    'asset' => 'EXA',
]);
```

### Admin Actions

```php
// Adjust User Balance
$auditService->logAdmin($admin->id, 'adjust_balance', [
    'user_id' => 123,
    'asset' => 'USD',
    'adjustment' => 1000,
    'reason' => 'Promotion credit',
]);

// Ban User
$auditService->logAdmin($admin->id, 'ban_user', [
    'user_id' => 123,
    'reason' => 'Suspicious activity',
    'duration_days' => 30,
]);

// Freeze User
$auditService->logAdmin($admin->id, 'freeze_user', [
    'user_id' => 123,
    'reason' => 'Account under review',
]);

// Edit Reward
$auditService->logAdmin($admin->id, 'edit_reward', [
    'reward_id' => 456,
    'old_value' => 50,
    'new_value' => 100,
    'reason' => 'Increased promotion budget',
]);
```

---

## Security Rules

### Immutability
- ✅ Logs **cannot be edited** after creation
- ✅ Logs **cannot be deleted** (only admins can archive/purge old logs)
- ✅ All changes are tracked in the database

### Access Control
- **Users** can view their own activity logs only
- **Admins** can view all user activity logs
- **Super Admins** can view admin action logs
- **Read-only** - No user can modify their own logs

### Data Storage
- IP addresses stored in full (IPv4 & IPv6)
- User agent strings captured for device detection
- JSON data for flexible context storage
- Nullable fields for optional context

### Performance
- Indexes on all filter columns
- Pagination (default 20/page, max 100/page)
- Efficient queries with proper joins
- Archive old logs (>90 days) to cold storage

---

## Performance Optimization

### Indexes
```sql
CREATE INDEX idx_activity_logs_user_id ON activity_logs(user_id);
CREATE INDEX idx_activity_logs_admin_id ON activity_logs(admin_id);
CREATE INDEX idx_activity_logs_type ON activity_logs(type);
CREATE INDEX idx_activity_logs_action ON activity_logs(action);
CREATE INDEX idx_activity_logs_created_at ON activity_logs(created_at);
CREATE INDEX idx_activity_logs_user_id_created_at ON activity_logs(user_id, created_at);
CREATE INDEX idx_activity_logs_type_created_at ON activity_logs(type, created_at);
```

### Query Optimization
- Always use pagination
- Filter by date range when possible
- Limit query result sets
- Use database-level indexes

---

## Fraud Detection Integration

### Detect Suspicious Login
```php
$failedAttempts = ActivityLog::query()
    ->where('user_id', $user->id)
    ->where('type', 'auth')
    ->where('action', 'login_failed')
    ->where('created_at', '>=', now()->subHour())
    ->count();

if ($failedAttempts > 5) {
    // Trigger security alert
}
```

### Detect Multiple IP Login
```php
$ips = ActivityLog::query()
    ->where('user_id', $user->id)
    ->where('action', 'login')
    ->distinct('ip')
    ->where('created_at', '>=', now()->subHour())
    ->count();

if ($ips > 3) {
    // Require 2FA verification
}
```

### Detect Large Withdrawal
```php
$recentWithdrawal = ActivityLog::query()
    ->where('user_id', $user->id)
    ->where('action', 'withdrawal_requested')
    ->where('created_at', '>=', now()->subDay())
    ->get();

$totalAmount = $recentWithdrawal->sum(fn($log) => $log->data['amount'] ?? 0);

if ($totalAmount > 10000) {
    // Require admin approval
}
```

---

## Testing

### Test User Logs
```php
public function test_user_can_view_own_activity_logs()
{
    $user = User::factory()->create();
    $log = ActivityLog::factory()->create(['user_id' => $user->id]);

    $response = $this->actingAs($user)
        ->getJson('/api/logs/my-activity');

    $response->assertOk()
        ->assertJsonPath('data.0.id', $log->id);
}
```

### Test Admin Logs
```php
public function test_admin_can_view_all_activity_logs()
{
    $admin = User::factory()->admin()->create();
    $logs = ActivityLog::factory(5)->create();

    $response = $this->actingAs($admin)
        ->getJson('/admin/logs/activity');

    $response->assertOk()
        ->assertJsonCount(5, 'data');
}
```

### Test Log Immutability
```php
public function test_activity_logs_cannot_be_deleted()
{
    $log = ActivityLog::factory()->create();

    $this->assertThrows(Exception::class, function () use ($log) {
        $log->delete();
    });
}
```

---

## Configuration

### Add to `.env`

```env
# Audit Logging
AUDIT_LOG_RETENTION_DAYS=365
AUDIT_LOG_ARCHIVE_AFTER_DAYS=90
AUDIT_LOG_ARCHIVE_ENABLED=true
```

### Usage in Config

```php
// config/audit.php
return [
    'retention_days' => env('AUDIT_LOG_RETENTION_DAYS', 365),
    'archive_after_days' => env('AUDIT_LOG_ARCHIVE_AFTER_DAYS', 90),
    'archive_enabled' => env('AUDIT_LOG_ARCHIVE_ENABLED', true),
];
```

---

## Migration & Deployment

### Run Migration
```bash
php artisan migrate
```

### Verify Schema
```bash
php artisan tinker
> Schema::getColumns('activity_logs')
```

### Test Logging
```bash
php artisan tinker
> $log = \App\Models\ActivityLog::factory()->create();
> $log->toArray()
```

---

## Troubleshooting

### Logs Not Recording
1. Verify middleware is registered in `bootstrap/app.php`
2. Check `ActivityAuditService` is properly injected
3. Verify database migration ran: `php artisan migrate --list`

### Slow Query Performance
1. Check indexes exist: `SELECT * FROM pg_indexes WHERE tablename='activity_logs';`
2. Use `EXPLAIN ANALYZE` on slow queries
3. Archive old logs to cold storage

### High Disk Usage
1. Enable log archiving in config
2. Run: `php artisan audit:archive`
3. Purge old logs: `php artisan audit:purge --days=365`

---

## Future Enhancements

- [ ] Real-time log streaming via WebSocket
- [ ] Elasticsearch integration for advanced search
- [ ] Machine learning fraud detection
- [ ] GDPR-compliant data export/deletion
- [ ] Log anonymization for privacy
- [ ] Automated alert rules
- [ ] Log retention policies
- [ ] Audit report generation

---

## Summary

The ExaEarn Audit & Activity Log System provides:
- ✅ **Immutable logs** - Cannot be edited or deleted
- ✅ **Fraud detection** - Suspicious patterns easily identified
- ✅ **Compliance** - Exchange-grade audit trail
- ✅ **Performance** - Indexed queries, pagination
- ✅ **Security** - IP/device tracking, type-specific logging
- ✅ **Admin control** - Full visibility into all platform activities
- ✅ **Production ready** - Battle-tested, performant architecture

**Status**: Ready for production deployment ✅
