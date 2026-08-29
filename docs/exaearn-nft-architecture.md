# ExaEarn NFT Marketplace Architecture

The NFT marketplace now uses a service-backed architecture:

`NftController -> NftService -> LedgerService / BlockchainService / NFT read models`

The service supports collection creation, metadata-backed mint requests, listings, fixed-price purchases, auctions, upgrades, subscriptions, blockchain event sync and reconciliation.

Blockchain operations fail closed. If the blockchain service is not configured, mint requests remain pending with an auditable `PENDING_PROVIDER_CONFIGURATION` chain transaction. The product does not claim confirmed minting or final ownership before chain confirmation.

