# Audit Log System - Quick Start Guide

## Quick Setup (2 minutes)

### 1. Run Migration
```bash
php artisan migrate
```

### 2. Test Installation
```bash
php artisan tinker
> \App\Models\ActivityLog::factory()->create()
> \App\Models\ActivityLog::count()
```

---

## Quick Examples

### User: View My Activity Logs
```bash
curl -X GET http://localhost:8000/api/logs/my-activity \
  -H "Authorization: Bearer YOUR_TOKEN"
```

**Response**:
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "type": "auth",
      "action": "login",
      "ip": "192.168.1.1",
      "device": "Mozilla/5.0...",
      "status": "success",
      "created_at": "2026-05-09T10:30:00Z"
    }
  ],
  "pagination": {
    "total": 50,
    "per_page": 20,
    "current_page": 1
  }
}
```

### User: View Activity Summary
```bash
curl -X GET http://localhost:8000/api/logs/summary \
  -H "Authorization: Bearer YOUR_TOKEN"
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
      "trade": 200
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

### Admin: View All Activity Logs
```bash
curl -X GET 'http://localhost:8000/admin/logs/activity?page=1&per_page=20&type=wallet' \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

### Admin: View Specific User's Logs
```bash
curl -X GET http://localhost:8000/admin/logs/user/123 \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

### Admin: Get Suspicious Activity Report
```bash
curl -X GET 'http://localhost:8000/admin/logs/suspicious?days=7' \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

### Admin: Get IP Activity
```bash
curl -X GET 'http://localhost:8000/admin/logs/ip-activity?ip=192.168.1.1' \
  -H "Authorization: Bearer ADMIN_TOKEN"
```

---

## Service Usage Examples

### Log a Wallet Deposit
```php
use App\Services\ActivityAuditService;

public function deposit(Request $request, ActivityAuditService $audit)
{
    $user = $request->user();
    
    // ... process deposit ...
    
    // Log the deposit
    $audit->logWallet($user->id, 'deposit', [
        'amount' => 100,
        'asset' => 'USDT',
        'network' => 'TRC20',
    ]);
}
```

### Log a Trade Order
```php
$audit->logTrade($user->id, 'order_created', [
    'pair' => 'BTC/USD',
    'side' => 'buy',
    'amount' => 0.5,
    'price' => 50000,
    'order_id' => 'ord_123',
]);
```

### Log an Admin Action
```php
$audit->logAdmin($admin->id, 'adjust_balance', [
    'user_id' => 123,
    'adjustment' => 1000,
    'reason' => 'Promotion credit',
]);
```

### Log a Reward
```php
$audit->logReward($user->id, 'checkin_reward', [
    'amount' => 10,
    'asset' => 'EXA',
]);
```

### Log a Staking Action
```php
$audit->logStaking($user->id, 'stake', [
    'pool_id' => 1,
    'amount' => 1000,
    'duration' => 30,
]);
```

---

## Filtering Examples

### Filter by Type
```bash
# Get wallet activities only
curl -X GET 'http://localhost:8000/api/logs/my-activity?type=wallet'
```

### Filter by Status
```bash
# Get failed authentication attempts
curl -X GET 'http://localhost:8000/api/logs/my-activity?type=auth&status=failed'
```

### Filter by Date Range
```bash
# Get activities from last 7 days
curl -X GET 'http://localhost:8000/api/logs/my-activity?from_date=2026-05-02&to_date=2026-05-09'
```

### Combined Filters
```bash
# Get failed login attempts from last 24 hours
curl -X GET 'http://localhost:8000/admin/logs/activity?type=auth&action=login_failed&from_date=2026-05-08'
```

---

## Auto-Logging via Middleware

Activities are automatically logged when you use the `log.activity` middleware:

```php
Route::middleware(['auth:sanctum', 'log.activity'])->group(function () {
    Route::post('wallet/deposit', [WalletController::class, 'deposit']);
    Route::post('trade/order', [TradeController::class, 'createOrder']);
    Route::post('rewards/claim', [RewardController::class, 'claim']);
});
```

The middleware automatically:
- ✅ Detects the route type (wallet, trade, reward, etc.)
- ✅ Extracts relevant data from the request
- ✅ Captures IP and device information
- ✅ Logs success/failure based on response status
- ✅ Stores everything in the database

---

## Common Queries

### Find All Failed Logins for a User
```php
$failedLogins = \App\Models\ActivityLog::query()
    ->where('user_id', 123)
    ->where('type', 'auth')
    ->where('action', 'login_failed')
    ->where('created_at', '>=', now()->subDay())
    ->get();
```

### Find All Withdrawals in Last 24 Hours
```php
$withdrawals = \App\Models\ActivityLog::query()
    ->where('action', 'withdrawal_requested')
    ->where('created_at', '>=', now()->subDay())
    ->get();
```

### Find All Admin Actions by Specific Admin
```php
$adminActions = \App\Models\ActivityLog::query()
    ->where('admin_id', $adminId)
    ->where('type', 'admin')
    ->orderByDesc('created_at')
    ->get();
```

### Find All Activities from a Specific IP
```php
$ipActivities = \App\Models\ActivityLog::query()
    ->where('ip', '192.168.1.1')
    ->where('created_at', '>=', now()->subDay())
    ->get();
```

### Get Suspicious Login Attempts
```php
$suspicious = \App\Models\ActivityLog::query()
    ->where('type', 'auth')
    ->where('status', 'failed')
    ->where('created_at', '>=', now()->subHour())
    ->groupBy('user_id')
    ->havingRaw('count(*) > 5')
    ->get();
```

---

## Testing

### Run All Audit Log Tests
```bash
php artisan test tests/Feature/AuditLogTest.php
```

### Run Specific Test
```bash
php artisan test tests/Feature/AuditLogTest.php --filter test_login_logs_activity
```

### Test With Output
```bash
php artisan test tests/Feature/AuditLogTest.php -v
```

---

## Real-World Integration Example

```php
// In WalletController
use App\Services\ActivityAuditService;

class WalletController extends Controller
{
    public function __construct(
        private ActivityAuditService $audit
    ) {}

    public function withdraw(Request $request)
    {
        $user = $request->user();
        $validated = $request->validate([
            'amount' => 'required|numeric|min:1',
            'address' => 'required|string',
        ]);

        try {
            // Process withdrawal
            $withdrawal = $this->processWithdrawal($user, $validated);

            // Log successful withdrawal
            $this->audit->logWallet($user->id, 'withdrawal_requested', [
                'amount' => $validated['amount'],
                'address' => $validated['address'],
                'status' => 'pending',
                'withdrawal_id' => $withdrawal->id,
            ], 'success');

            return response()->json([
                'success' => true,
                'withdrawal_id' => $withdrawal->id,
            ]);

        } catch (Exception $e) {
            // Log failed withdrawal
            $this->audit->logWallet($user->id, 'withdrawal_failed', [
                'amount' => $validated['amount'],
                'address' => $validated['address'],
                'reason' => $e->getMessage(),
            ], 'failed');

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }
}
```

---

## Performance Tips

1. **Always paginate**
   ```php
   $logs = ActivityLog::query()->paginate(20); // Don't: ->get()
   ```

2. **Use indexes for filtering**
   ```php
   // Fast: Uses index on (user_id, created_at)
   $logs = ActivityLog::byUser($userId)->where('created_at', '>=', now()->subDay())->get();
   ```

3. **Limit date ranges**
   ```php
   // Fast
   $logs = ActivityLog::where('created_at', '>=', now()->subDays(30))->get();
   
   // Slow
   $logs = ActivityLog::get(); // No date filter
   ```

4. **Archive old logs**
   ```bash
   php artisan audit:archive --days=90
   ```

---

## Troubleshooting

### Logs not showing up?
1. Check middleware is enabled: `bootstrap/app.php`
2. Verify route uses middleware: `Route::middleware('log.activity')`
3. Verify `ActivityAuditService` is injected
4. Run: `php artisan migrate`

### Slow queries?
1. Check indexes exist: `SHOW INDEX FROM activity_logs;`
2. Use pagination: `?page=1&per_page=20`
3. Add date filter: `?from_date=2026-05-01`

### High disk usage?
1. Enable archiving: `AUDIT_LOG_ARCHIVE_ENABLED=true`
2. Run: `php artisan audit:archive`
3. Purge old logs: `php artisan audit:purge --days=365`

---

## Security Reminders

✅ Users can only view their own logs  
✅ Admins can view all logs  
✅ Logs cannot be edited or deleted  
✅ All IP addresses are logged  
✅ All devices are logged  
✅ All failures are logged for fraud detection  
✅ Admin actions are tracked separately  

---

## Next Steps

1. Read [AUDIT_LOG_GUIDE.md](./AUDIT_LOG_GUIDE.md) for comprehensive documentation
2. Check [AUDIT_LOG_SYSTEM_IMPLEMENTATION.md](./AUDIT_LOG_SYSTEM_IMPLEMENTATION.md) for details
3. Run tests to verify installation: `php artisan test tests/Feature/AuditLogTest.php`
4. Deploy to production and monitor performance

---

**Questions? Check the full documentation in AUDIT_LOG_GUIDE.md** 📚
