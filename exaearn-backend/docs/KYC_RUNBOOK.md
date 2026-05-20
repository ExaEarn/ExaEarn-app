# KYC Go-Live Runbook

## 1) Environment
Set these values in `.env`:

- `QUEUE_CONNECTION=redis`
- `KYC_PROVIDER=smile`
- `KYC_FALLBACK_PROVIDER=sumsub`
- `SMILE_BASE_URL=...`
- `SMILE_API_KEY=...`
- `SUMSUB_BASE_URL=...`
- `SUMSUB_API_KEY=...`
- `KYC_BLACKLIST_COUNTRIES=KP,IR,SY`

## 2) Database
Run:

```bash
php artisan migrate --force
```

## 3) Queue workers
Run at least one worker for KYC and notifications:

```bash
php artisan queue:work redis --queue=kyc,notifications --tries=3 --timeout=120
```

## 4) Sandbox provider test
Verify provider and fallback wiring:

```bash
php artisan kyc:sandbox-check
```

The command prints responses for:
- verifyDocument
- verifyFace
- checkDuplicate
- checkCountry

## 5) API smoke tests
User endpoint:
- `POST /api/kyc/upload`

Admin endpoints:
- `GET /api/admin/kyc/flagged`
- `GET /api/admin/kyc/{id}`
- `POST /api/admin/kyc/approve`
- `POST /api/admin/kyc/reject`

## 6) Expected workflow
- upload -> `pending`
- verify job + risk job run in Redis queue
- low risk + auto verified -> `auto_approved` and `users.kyc_level` updated
- medium risk -> `flagged` for admin review
- high risk / duplicate / blacklisted / fake id -> `rejected`

## 7) Withdrawal limits by KYC level
Configured in `config/kyc.php`:
- level0: 100/day
- level1: 1000/day
- level2: 10000/day
- level3: very high ceiling (acts as unlimited)

