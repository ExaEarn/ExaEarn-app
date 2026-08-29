# ExaEarn NFT Reconciliation

`NftService::reconciliation()` checks marketplace read models for owner/listing mismatches and pending chain finality.

Blocking ownership mismatches create `nft_reconciliation_breaks` records for operations review. Pending finality is reported as informational unless it becomes stale or inconsistent.

Chain reorg events are fail-closed. The affected NFT is moved to manual review, active listings are suspended, and an `nft_reconciliation_breaks` record is opened with critical severity. Operations can then review the chain evidence without the marketplace continuing to present the asset as normally tradeable.
