# EXA Flight Preimplementation Audit

## Scope

This audit verified the current EXA Flight implementation before hardening changes.

## Located Implementation

- Backend service: `backend/api-gateway/app/Services/FlightGameService.php`
- Fairness service: `backend/api-gateway/app/Services/FlightFairnessService.php`
- User controller: `backend/api-gateway/app/Http/Controllers/FlightGameController.php`
- Admin controller: `backend/api-gateway/app/Http/Controllers/Admin/FlightGameAdminController.php`
- Models: `FlightGameRound`, `FlightGameBet`, `FlightGameSetting`, `FlightGameAuditLog`
- Tables: `flight_game_rounds`, `flight_game_bets`, `flight_game_settings`, `flight_game_audit_logs`
- Web UI: `apps/web/src/Game/Game.jsx`
- Web API client: `apps/web/src/services/gameFiApi.js`
- Existing tests: `backend/api-gateway/tests/Feature/FlightGameTest.php`, `GameFiFlowTest.php`

## Money Flow Found

Real-money participation previously used the canonical ledger foundation:

1. User funding account debited.
2. User `game_locked` account credited.
3. On cashout, `game_locked` debited and user funding credited for stake plus payout.
4. Treasury account is debited for profit.
5. On loss, `game_locked` is debited and `game_treasury` is credited.

No separate game wallet was found.

## Gaps Found

- Product classification existed only implicitly in the game UX and settings.
- Public real-money mode needed a hard legal/regulatory software gate.
- Demo/free-play mode needed explicit no-liability semantics.
- Participation needed Phase 16 compliance and Phase 18 security checks.
- Responsible gaming/self-exclusion controls were missing.
- Treasury exposure was not checked before accepting real-money entries.
- Account closure did not include unresolved game entries or game risk cases.
- Reconciliation existed through ledger tests but not a dedicated game reconciliation service.

## Preserved Foundation

The implementation keeps the existing round engine, fairness service, realtime publishing, canonical ledger entries, idempotency key behavior, cashout logic, auto-cashout, loss settlement, and admin summary.
