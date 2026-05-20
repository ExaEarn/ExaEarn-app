# ExaEarn Institutional Multi-Chain HD Wallet Security Architecture (v2)

## 1) Executive Security Objective

Build a **bank-grade, exchange-class custody and transaction platform** for real user funds with these invariants:

- No single breach can drain funds.
- No internal actor can unilaterally move funds.
- Every critical action is authenticated, authorized, logged, and auditable.
- System remains safe and operational during partial failures.

This design assumes **Zero Trust** and **continuous compromise simulation**.

---

## 2) Target Architecture (Microservices + Defense in Depth)

```mermaid
flowchart TB
    subgraph Client Layer
        U[User / Admin Console]
    end

    subgraph Edge & Identity
        GW[API Gateway + WAF]
        IAM[AuthN/AuthZ + MFA Service]
        DEV[Device Fingerprint + Session Risk]
    end

    subgraph Core Services (Private Network)
        WAL[Wallet Service\n(HD Address Mgmt + Gap Scanner)]
        TXN[Transaction Orchestrator]
        RISK[Risk/Policy Engine]
        AML[AML Screening Service]
        RECON[Balance Reconciliation Service]
        REBAL[Rebalancing Scheduler]
        MON[Monitoring + SIEM Forwarder]
        AUDIT[Immutable Audit Log Service]
    end

    subgraph Signing Zone (Isolated)
        SIGN[Isolated Signing Service]
        MPCA[MPC Party A]
        MPCB[MPC Party B]
        MPCC[MPC Party C (HSM/Enclave)]
    end

    subgraph Chain Access Layer
        NODEP[Primary Self-Hosted Nodes]
        NODES[Secondary Trusted RPC Providers]
        XVAL[Cross-Node Validator]
    end

    subgraph Security Control Plane
        KILL[Global/Chain Kill Switch]
        IR[Incident Response Automation]
        VAULT[Vault/KMS Secret Manager]
    end

    U --> GW --> IAM --> TXN
    IAM --> DEV
    TXN --> RISK --> AML
    TXN --> WAL
    TXN --> SIGN
    SIGN --> MPCA
    SIGN --> MPCB
    SIGN --> MPCC
    SIGN --> XVAL --> NODEP
    XVAL --> NODES
    WAL --> REBAL
    WAL --> RECON
    TXN --> AUDIT
    WAL --> AUDIT
    RISK --> AUDIT
    AML --> AUDIT
    RECON --> MON
    MON --> IR --> KILL
    VAULT --> IAM
    VAULT --> TXN
    VAULT --> SIGN
```

---

## 3) MPC-Based Key Management (Mandatory)

## 3.1 Key Strategy
- Use threshold MPC with **3 parties, 2-of-3 signing threshold** for each custody key.
- No single service ever reconstructs full private key material.
- One share must be hardware-backed (HSM or cloud enclave).

## 3.2 Share Placement
- **Party A:** signing cluster in isolated VPC subnet.
- **Party B:** independent service account / independent host group.
- **Party C:** HSM-backed enclave service with strict policy and attestation.

## 3.3 Operational Controls
- Separate IAM roles and credentials per MPC party.
- Independent deployment pipelines and approvals per party.
- Quorum-based approval for policy changes affecting key usage.
- Automatic key rotation policy (scheduled + emergency rotation).

## 3.4 Supported Chains
- EVM chains (ETH/Base/BNB/Polygon etc.) via ECDSA/secp256k1 MPC.
- TRON (secp256k1) via dedicated signing adapter.
- Additional chain adapters plug into the same signing intent API.

---

## 4) Secure API Design (Core Flows)

All internal service-to-service traffic uses mTLS, request signing, and short-lived service tokens.

## 4.1 External API (Gateway)
- `POST /v1/wallets/{userId}/withdrawals/quote`
- `POST /v1/wallets/{userId}/withdrawals`
- `POST /v1/wallets/{userId}/withdrawals/{requestId}/confirm-2fa`
- `GET /v1/wallets/{userId}/addresses/{chain}`
- `POST /v1/admin/security/kill-switch` (RBAC + step-up auth)

## 4.2 Internal APIs
- `txn-orchestrator -> risk-engine: POST /internal/risk/evaluate-withdrawal`
- `txn-orchestrator -> aml-service: POST /internal/aml/screen-address`
- `txn-orchestrator -> signing-service: POST /internal/signing/create-intent`
- `signing-service -> chain-adapter: POST /internal/tx/broadcast`
- `wallet-service -> recon-service: POST /internal/recon/snapshot`

## 4.3 Security Requirements for APIs
- Idempotency keys for every withdrawal request.
- Replay-safe signatures and timestamp windows.
- Strict schema validation and allowlisted fields.
- Full request/decision logging with correlation IDs.

---

## 5) Transaction Policy Engine (Real-Time Risk Engine)

## 5.1 Inputs
- User KYC tier, account age, historical behavior.
- Geo/IP intel, ASN risk, TOR/proxy indicators.
- Device fingerprint continuity.
- Velocity metrics (count, amount, destination churn).
- Time-based anomalies and session risk.

## 5.2 Decision States
- `APPROVE`
- `FLAG`
- `DELAY`
- `MANUAL_REVIEW`
- `BLOCK`

## 5.3 Sample Policy Logic
```text
IF login_country != withdrawal_country
   AND session_risk_score > 70
   THEN BLOCK

IF new_destination_address = true
   THEN apply timelock(24h) unless user is institutional allowlisted

IF user_daily_withdrawal + amount > user_limit
   THEN MANUAL_REVIEW

IF global_24h_withdrawal > global_threshold
   THEN DELAY and alert treasury
```

## 5.4 Risk Scoring
- Weighted model + deterministic hard-block rules.
- Hard rules for sanctions/high-risk addresses override score.
- Model outputs and rule triggers are both logged for audit explainability.

---

## 6) AML / Compliance Address Risk Scoring

Pre-broadcast screening is mandatory on every destination.

## 6.1 Integrations
- Chainalysis
- TRM Labs
- Elliptic

## 6.2 Decision Matrix
- **High Risk / Sanction Match / Hack Wallet:** `BLOCK`
- **Medium Risk / Mixed Signals:** `MANUAL_REVIEW`
- **Low Risk:** continue pipeline

## 6.3 Enforcement
- Cache results briefly (minutes) with forced refresh before final broadcast.
- Provider disagreement policy: fail-closed for high confidence negative signals.

---

## 7) Wallet Rebalancing System (Hot/Warm/Cold)

## 7.1 Treasury Tiers
- **Hot Wallet:** fast withdrawals, limited balance.
- **Warm Wallet:** controlled intermediate reserve.
- **Cold Wallet:** primary reserve, isolated signing path.

## 7.2 Automated Rules
- If `hot_balance < hot_min` → refill from warm/cold based on policy.
- If `hot_balance > hot_max` → sweep excess to cold.
- Event-driven (large withdrawal) + scheduled sweeps.

## 7.3 Controls
- Rebalance actions require policy approval and full audit trail.
- Large rebalances can require human co-sign approvals.

---

## 8) Isolated Transaction Signing Service

## 8.1 Isolation Model
- Dedicated signing cluster in isolated subnet.
- No direct public internet egress for cold signing path.
- One-way request ingress through authenticated internal gateway.

## 8.2 Request Contract
Signing service only accepts **signed internal intents** containing:
- chain id
- nonce policy
- destination + amount
- risk decision proof
- aml decision proof
- policy version hash

## 8.3 Signing Guardrails
- Verify chain ID and transaction domain separation.
- Nonce lock and uniqueness check.
- Expiry window enforcement.
- Reject if kill switch active.

---

## 9) HD Wallet Gap Limit Handling

## 9.1 Address Derivation
- BIP32/BIP44 derivation per chain/account path.
- Separate derivation branches for deposit/change/operations.

## 9.2 Gap Limit
- Maintain active scan window (e.g., 20 unused addresses).
- If address activity detected near edge, advance window automatically.
- Background scanner continuously watches chains for deposits.

## 9.3 Data Integrity
- Address index state stored transactionally.
- Recovery process can rebuild index cursor from chain events.

---

## 10) EVM Replay Attack Protection

- Enforce exact chain ID match at signing and broadcast.
- Strict nonce reservation and conflict handling.
- Transaction uniqueness checks (`to`, `value`, `data`, `nonce`, `chainId`).
- Use EIP-155 compatible signing and replacement transaction policies.

---

## 11) Token Contract Security Layer

Before crediting deposits:
- Contract address must be in allowlist registry.
- Verify expected token standard behavior (ERC20/TRC20/SPL adapter checks).
- Validate decimals/symbol/bytecode fingerprint where possible.
- Reject fake or malicious contracts and quarantine suspicious deposits.

---

## 12) Node Infrastructure (Hybrid)

## 12.1 Primary + Secondary
- Primary reads/writes through self-hosted full nodes.
- Fallback to trusted RPC providers on degradation.

## 12.2 Cross-Node Validation
- Compare balances, nonce, block head, and tx receipts across providers.
- Detect divergence and fail safely (pause affected chain actions if severe).

## 12.3 Reliability
- Health checks, circuit breakers, and automatic failover.

---

## 13) Incident Response & Kill Switch

## 13.1 Controls
- Global withdrawal freeze.
- Chain-specific disable.
- User account lock.
- Emergency admin override with dual-control approval.

## 13.2 Triggers
- Abnormal withdrawal velocity spikes.
- Intrusion detection alerts.
- Reconciliation mismatch over threshold.
- Unauthorized policy/config mutation attempts.

## 13.3 Procedure
- Freeze impacted flows immediately.
- Preserve forensic logs and snapshots.
- Require signed postmortem and approval for unfreeze.

---

## 14) Continuous Security Testing

- Automated dependency and container vulnerability scanning in CI.
- SAST/DAST on every release branch.
- Quarterly external penetration testing.
- Public/private bug bounty program.
- Continuous attack simulation for withdrawal flow.

---

## 15) User-Level Security Controls

- Mandatory TOTP 2FA for withdrawals.
- Email confirmation and anti-phishing copy.
- New destination address timelock (default 24h).
- Device fingerprint and IP history anomaly checks.
- Step-up verification for high-risk sessions.

---

## 16) Secret Management

Never store sensitive material in plaintext `.env` for production.

Use one of:
- HashiCorp Vault
- AWS KMS + Secrets Manager
- Equivalent enterprise secret platform

Store:
- API keys
- MPC credentials/tokens
- Encryption keys
- service credentials

Implement short TTL credentials, rotation, and access audit.

---

## 17) Real-Time Balance Reconciliation

Continuously compare:
- On-chain balances
- Internal ledger balances

Detect and alert:
- Missing funds
- Double credits
- Confirmation mismatch
- Chain reorg-related inconsistencies

Mismatch actions:
- Auto-hold affected account/asset flow.
- Trigger incident workflow and forensic trace.

---

## 18) Audit & Logging Model

- Append-only immutable audit stream (WORM-backed storage recommended).
- Log all transactions, admin actions, risk decisions, policy updates.
- Tamper-evident hashing (hash chain / signed log blocks).
- Queryable by compliance, security, and finance teams.

Minimum fields:
- `event_id`, `timestamp`, `actor`, `service`, `request_id`, `correlation_id`
- `decision`, `policy_version`, `risk_signals`, `outcome`

---

## 19) Deployment Strategy (Docker + Kubernetes)

## 19.1 Environment Segmentation
- Separate clusters/accounts for `dev`, `staging`, `prod`.
- Production split into trust zones: edge, core, signing, data.

## 19.2 Kubernetes Controls
- NetworkPolicies: default deny, explicit allow.
- Pod Security standards (restricted), read-only FS where possible.
- Workload identity (no long-lived static credentials).
- HPA + PDB for high availability.

## 19.3 CI/CD Security
- Signed container images + provenance attestation.
- Admission policies (only trusted images).
- Progressive rollout (canary/blue-green).
- Manual approval gates for signing/risk policy services.

## 19.4 Data Layer
- Encrypted at rest and in transit.
- PITR backups + cross-region replication.
- Periodic restore drills.

---

## 20) Secure Withdrawal Flow (End-to-End)

1. User initiates withdrawal.
2. IAM validates session + MFA + device posture.
3. Policy engine computes risk decision.
4. AML service screens destination address.
5. Transaction orchestrator applies limits, timelock, and kill-switch checks.
6. Signing intent sent to isolated signing service.
7. MPC threshold signing executed (2-of-3).
8. Broadcast via validated node path.
9. Confirmation monitor updates ledger.
10. Reconciliation verifies balances; all actions written to immutable audit log.

---

## 21) Implementation Checklist

## Phase 1 (Foundation)
- [ ] Establish wallet microservices and isolated signing zone.
- [ ] Integrate Vault/KMS and remove sensitive prod values from `.env`.
- [ ] Implement immutable audit schema and correlation IDs.

## Phase 2 (Custody Hardening)
- [ ] Integrate MPC provider with 2-of-3 threshold.
- [ ] Enforce signing intent verification and chain-specific adapters.
- [ ] Add nonce manager and replay protections.

## Phase 3 (Risk + Compliance)
- [ ] Build risk engine with deterministic blocking rules.
- [ ] Integrate Chainalysis/TRM/Elliptic with pre-broadcast enforcement.
- [ ] Add manual review tooling and dual-control approvals.

## Phase 4 (Treasury + Reconciliation)
- [ ] Implement hot/warm/cold rebalancing automation.
- [ ] Implement real-time on-chain vs ledger reconciliation.
- [ ] Add anomaly-triggered incident workflows.

## Phase 5 (Operations + Assurance)
- [ ] Implement kill switch controls and runbooks.
- [ ] Enable SIEM integration + IDS alerts.
- [ ] Run pen test, chaos drills, and recovery game days.

---

## 22) Non-Negotiable Security Gates (Go-Live)

System is not production-ready until all are true:

- MPC threshold signing active in production.
- No unilateral admin withdrawal path exists.
- Mandatory MFA + address timelock enforced.
- AML checks are blocking, not advisory.
- Reconciliation and kill switch tested in drills.
- Immutable audit logs verifiably complete.

This design satisfies the requested objective for institutional-grade, resilient, and compliance-driven multi-chain wallet infrastructure.
