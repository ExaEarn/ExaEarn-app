# ExaEarn Developer Documentation Audit

## Existing foundation

The repository already contained a working Phase 14 backend: environment-scoped API keys, `exa_test_`/`exa_live_` prefixes, HMAC-SHA256 authentication, nonce replay protection, timestamp validation, permissions, IP allowlists, request IDs, isolated sandbox balances, canonical product routing, durable private event replay, signed webhooks, OpenAPI, and a TypeScript SDK.

The portal itself was a single long marketing page. It listed capabilities but did not provide route-level contracts, deep links, global search, recovery procedures, explicit rate budgets, a searchable error model, or compact documentation navigation.

## Requirement classification

| Area | Initial state | Final action |
|---|---|---|
| Developer landing page | COMPLETE | Preserved as the expressive entry page. |
| Documentation information architecture | MISSING | Added route-aware docs shell, sidebar, TOC, mobile drawer, and deep links. |
| Global search | MISSING | Added Ctrl/Cmd+K search across endpoint names, paths, scopes, streams, and descriptions. |
| REST backend | COMPLETE/PARTIAL | Reused; added only canonical server time and error timestamps/details. |
| Endpoint reference | PARTIAL | Added typed reference catalog for the stable and high-value restricted routes. |
| Authentication documentation | PARTIAL | Documented the exact canonical string and runnable TypeScript, Python, and PHP signing logic. |
| Scope documentation | PARTIAL | Added risk classification using actual configured scope names. |
| Rate limits | PARTIAL | Documented configured public, private, trading, and withdrawal budgets. |
| Error model | PARTIAL | Added canonical timestamp/details fields and searchable error guidance. |
| OpenAPI | PARTIAL | Added server-time and canonical error schemas; broader route coverage remains incremental. |
| TypeScript SDK | COMPLETE/PARTIAL | Reused and added `getServerTime()`. |
| Python SDK | MISSING | Clearly marked unpublished; REST examples are provided without claiming a package. |
| Public market WebSocket transport | PARTIAL | Session, sequencing, replay, and policies exist; a separately deployable socket transport/status remains operational infrastructure. |
| Webhooks | COMPLETE | Documented real signing, retry, DLQ, stable event ID, and replay behavior. |
| Interactive explorer | PARTIAL | Safe UI boundary exists but execution remains disabled pending authenticated console integration. |
| Live status telemetry | MISSING | Capability status is shown; uptime is not fabricated. |

## Product exposure

Spot, Convert, market data, wallet reads, staking software contracts, realtime replay, and webhooks are documented as Stable where their software paths support it. Futures, Margin, Copy Trading, ExaAI, and withdrawals retain Restricted classification and their underlying risk, governance, eligibility, and approval gates.

