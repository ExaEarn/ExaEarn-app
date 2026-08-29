# ExaEarn NFT Preimplementation Audit

| Component | Status | Finding |
| --- | --- | --- |
| Web marketplace | PARTIAL | Existing web route and API client existed. |
| NFT controller/routes | BROKEN | Controller referenced `App\Services\NftService`, but the service file was missing. |
| NFT models/migrations | PARTIAL | Collections, NFTs, listings, sales, auctions and related finance tables existed. |
| Ledger integration | MISSING | NFT purchase settlement was not service-backed. |
| Blockchain integration | PARTIAL | `BlockchainService` had NFT methods but no marketplace service orchestration. |
| Ownership projection | PARTIAL | `nfts.user_id` existed but ownership verification was not enforced by service logic. |
| Reconciliation | MISSING | No NFT reconciliation breaks or service checks were present. |
| Admin | PARTIAL | Generic admin model routes existed; dedicated operations remain future UI work. |
| External RPC/gas/legal | EXTERNAL_REQUIREMENT | Real chain finality, gas wallets and legal/IP policy require operational setup. |

