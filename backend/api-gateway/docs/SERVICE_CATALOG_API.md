# Service Catalog API

The service directory is configured in `config/navigation.php`. Routes in that registry must be internal application paths; service metadata is allowlisted before it is returned to clients.

## Endpoints

### `GET /api/public/services`

Returns public service metadata and public availability. Responses are cached for the configured `navigation.catalog_cache_seconds` duration (300 seconds by default). The response contains categories, services, Quick Access defaults, primary navigation, Trade subnavigation, app availability, and the Support route.

### `GET /api/me/services`

Requires the existing Sanctum user authentication. Returns the same catalog with per-user compliance availability, the user's resolved Quick Access shortcuts, and their existing language and display-currency preferences. User-specific responses are not cached.

Service entries include an internal `route`, `route_target`, approved `icon_key`, status and search metadata, `available`, and a safe `reason_code` when unavailable. Compliance product codes and feature-flag config keys are not returned.

### Quick Access preferences

The existing authenticated `GET` and `PUT /api/preferences/dashboard` endpoints serve Quick Access. The canonical preference is `quick_access_services`, stored in the user's existing `preferences.dashboard` JSON. Updates accept up to four distinct public services marked Quick-Access-eligible in the registry. The legacy `quick_access` request field remains accepted for compatibility; responses include it as an alias.

Reads resolve saved IDs against current public availability without changing the saved preference. This allows a temporarily unavailable service to reappear if it becomes available again. User-specific availability uses the existing `CompliancePolicyService` and exposes only `auth_required`, `verification_required`, `jurisdiction_restricted`, `coming_soon`, or `temporarily_unavailable`.

The catalog is code-managed through `config/navigation.php`; no generic settings endpoint accepts service routes or external URLs.
