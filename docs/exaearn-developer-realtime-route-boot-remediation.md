# Developer realtime route boot remediation

## Root cause

`App\Http\Controllers\Controller` imports Laravel's `Illuminate\Foundation\Auth\Access\AuthorizesRequests` trait. That trait defines `authorize($ability, $arguments = [])` as the framework policy/gate helper. `DeveloperRealtimeAuthorityController` declared an API action named `authorize(Request $request): JsonResponse`, which PHP treated as an override with an incompatible parameter and return signature.

## Controller and route change

Before: `DeveloperRealtimeAuthorityController::authorize(Request): JsonResponse`.

After: `DeveloperRealtimeAuthorityController::authorizeRealtimeSession(Request): JsonResponse`.

The public/internal HTTP contract did not change:

| HTTP method | URI | Middleware | Before action | After action |
|---|---|---|---|---|
| POST | `/api/internal/developer/realtime/authorize` | `node.webhook` | `authorize` | `authorizeRealtimeSession` |
| POST | `/api/internal/developer/realtime/replay` | `node.webhook` | `replay` | `replay` |

Developer-facing realtime APIs such as `/api/developer/v1/realtime/session` and `/api/developer/v1/realtime/replay` are unchanged. The internal URL used by the realtime gateway is unchanged.

## Authorization semantics preserved

The renamed method keeps the same request validation, `JsonResponse` contract, and call to `DeveloperRealtimeService::authorizeTransport()`. The existing service still checks active session token, requested environment, active API key, active project/workspace/organization, active project environment, topic scopes, and production capability gates. The `node.webhook` shared-secret middleware remains on the internal route.

## Tests

Added `DeveloperRealtimeRouteBootTest` to prove the route collection resolves the internal authorization route to `authorizeRealtimeSession`, does not collide with Laravel's framework `authorize()` helper, rejects missing node secrets, rejects environment mismatch, authorizes a valid sandbox realtime session, revalidates scopes, and rejects revoked credentials.

## Validation

Route list boot: PASS. `php artisan route:list --path=internal/developer/realtime -v` boots and shows the unchanged authorize and replay URLs.

Route cache: PASS. `php artisan route:cache` completed successfully, followed by `php artisan route:clear` to avoid leaving generated cache artifacts.

PHP syntax: PASS for `DeveloperRealtimeAuthorityController.php` and `DeveloperRealtimeRouteBootTest.php`.

Developer realtime/platform regression: PASS, 68 tests and 2,013 assertions.

Listing P0 plus MarketData/Convert regression: PASS, 46 tests and 394 assertions.

Full backend: PASS, 633 passed, 0 failed, 1 skipped, 5,112 assertions.

Frontend: Developer typecheck/build PASS; Listing typecheck/build PASS. Lint is not configured for either package.
