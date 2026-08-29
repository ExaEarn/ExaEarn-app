# EXA Flight Responsible Gaming

## Control Plane

`FlightGamePolicyService` enforces responsible-gaming controls for real-money participation.

## Self-Exclusion

Supported states:

- `COOLDOWN`
- `SELF_EXCLUDED`
- `PERMANENTLY_EXCLUDED`

During active exclusion, new real-money entries are blocked. Settlement of existing obligations remains possible.

## Limits

Configurable settings include:

- Daily participation limit
- Weekly participation limit
- Monthly participation limit
- Daily loss limit
- Weekly loss limit
- Monthly loss limit
- Session participation limit

Limits are calculated from settled/recorded server-side entries, not frontend estimates.

## Public Real-Money Status

Real-money public mode remains disabled until legal/regulatory approval is configured.
