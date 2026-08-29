# ExaEarn NFT Blockchain Finality

NFT minting and transfer workflows distinguish local intent from chain confirmation.

Mint states include `PENDING`, `SUBMITTED`, `CONFIRMING`, `CONFIRMED`, `FAILED`, `DROPPED`, `REPLACED` and `REORG_PENDING`.

When RPC/provider infrastructure is not configured, ExaEarn stores the request as pending provider configuration. Confirmed ownership requires a synced blockchain event or verified custody/provider state.

