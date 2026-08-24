# ExaEarn Phase 15A Market Registry

Markets are created through the existing `markets` table.

Safe defaults:

- `status = pre_launch`
- `trading_status = PRE_LAUNCH`
- `engine_mode = NEW_SPOT_ENGINE`
- `last_price = 0`
- `external_routing_enabled = false`

Admin cannot set a manual live price. Market price must come from legitimate orders/fills/reference policy.

