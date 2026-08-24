# ExaEarn Phase 2C Spot Operations

## Operator Commands

```bash
php artisan spot:cutover-precheck {market}
php artisan spot:cutover {market} shadow
php artisan spot:cutover {market} prepare
php artisan spot:cutover {market} promote
php artisan spot:cutover {market} rollback
php artisan spot:cutover-canary {market} --amount=0.1 --price=1000
php artisan spot:replay {market}
php artisan spot:settlement-outbox --limit=100
php artisan spot:load-harness --orders=100 --market=CUT2C/USDT
```

## Health Signals

- orders accepted/rejected
- open orders
- lease owner and generation
- latest execution sequence
- latest realtime sequence
- latest snapshot sequence
- settlement outbox pending/retrying/failed
- replay checksum
- reconciliation failure counts

## Local Validation

`CUT2C/USDT` local PostgreSQL load validation:

- orders submitted: `20`
- orders accepted: `20`
- trades created: `10`
- error count: `0`
- replay last sequence: `20`
- settlement outbox pending: `0`

