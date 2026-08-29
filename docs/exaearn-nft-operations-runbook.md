# ExaEarn NFT Operations Runbook

## Blockchain/RPC Unavailable

Keep mint and transfer finality pending. Do not mark assets confirmed until provider health returns and chain events reconcile.

## Ownership Mismatch

Pause affected listing, open reconciliation break, verify chain/custody state, then resolve through audited operations.

## Duplicate Purchase Retry

Use the idempotency key. Replayed purchase calls must return the original sale and must not post a second ledger transaction.

## Legal/IP Report

Restrict public visibility where policy requires review. Do not delete immutable evidence.

