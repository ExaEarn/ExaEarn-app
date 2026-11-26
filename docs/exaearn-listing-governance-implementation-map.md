# ExaEarn Listing Governance Implementation Map

Date: 2026-08-31

## Verified ownership

| Capability | Classification | Canonical implementation |
|---|---|---|
| Applicant identity | EXISTING | ExaEarn user/Sanctum authentication |
| Applicant organizations | PARTIAL, EXTENDED | `listing_organizations` and member roles in `listing_team_members` |
| Applications | EXISTING, EXTENDED | `ListingApplication`, JSON-backed structured sections, idempotent draft updates |
| Review lifecycle | EXISTING, EXTENDED | `ListingLifecycleService`, explicit review states, information requests and rejection |
| Documents | PARTIAL, EXTENDED | Private `listing_documents`, versioning, SHA-256, authorization and scanner status |
| Admin operations | EXISTING | Existing Admin application and `ListingCenterController` under `listing.manage` RBAC |
| Contract validation | EXISTING | `ListingContractValidationService` and normalized validation records |
| Asset registry | EXISTING | Canonical `BlockchainAsset`; no parallel registry |
| Custody | EXISTING | Canonical custody/network configuration; deposits and withdrawals default disabled |
| Ledger | EXISTING | Canonical exchange ledger path; no Listing wallet or ledger |
| Markets | EXISTING | Canonical `Market`; initial status is pre-launch/disabled |
| Liquidity | EXISTING | Listing requirements plus market-maker readiness services |
| Maker-checker | EXISTING | Recommendation maker cannot approve; scheduler checker must differ |
| Launch orchestration | EXISTING | Idempotent launch events for announcement, deposit, trading and withdrawal controls |
| Emergency controls | EXISTING | Deposit, withdrawal, trading and full halt controls with audit reasons |
| Monitoring/migrations | EXISTING | Listing monitoring, token migration and audit records |
| Notifications | PARTIAL | Canonical notification/content infrastructure exists; complete provider delivery is operational configuration |
| Malware scanning | EXTERNAL_DEPENDENCY | Adapter state is `NOT_CONNECTED`; private uploads do not claim a scan passed |
| KYB/sanctions/blockchain analytics | EXTERNAL_DEPENDENCY | Existing compliance/provider architecture must be configured; no provider result is fabricated |

## Invariants

`APPLICATION != APPROVAL != INTEGRATION != LISTING != TRADING ACTIVATION`.

Approval creates an integration candidate only. Asset, network, custody, ledger, market, liquidity, tests, second-person approval and scheduled capability activation remain separate gates.

## Supported lifecycle

Applicant organization -> structured draft -> validation -> submission -> due diligence/review -> information request/response or rejection -> maker recommendation -> checker approval for integration -> canonical asset/network configuration -> contract validation -> disabled pre-launch market -> liquidity and integration tests -> final approval request -> separate checker scheduling -> independent deposit/trading/withdrawal events -> live monitoring -> emergency restriction/token migration/delisting controls.

## Remaining external work

- Connect and accredit a malware scanner before treating uploads as clean.
- Configure production KYB, sanctions and blockchain-analytics providers.
- Staff legal, compliance, security, custody, liquidity and market-operation review queues.
- Complete production storage encryption/retention/key-management evidence.
- Validate provider credentials, custody networks, market makers and launch runbooks in the target environment.
