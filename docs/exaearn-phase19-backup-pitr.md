# ExaEarn Phase 19 Backups and PITR

## Software Capability

READY. `SreBackupService` records backup metadata, hashes private storage references, tracks encryption, checksums, retention and restore-test status.

## PITR Policy

PostgreSQL PITR must preserve:

- ledger transactions and entries
- finance journals
- orders, fills and positions
- deposits and withdrawals
- P2P, fiat, OTC and market maker state
- compliance, finance, security and audit logs

## Truthful Status

Real backup restore drill: STAGING REQUIRED until an isolated production-like restore is executed and recorded.

