# ExaEarn Blockchain Network Architecture

## Network Registry

`CustodyRegistryService` synchronizes the canonical network and asset registry from `config/custody.php`.

Supported initial network families:

- EVM: Ethereum, Base, BNB Smart Chain, Polygon
- UTXO: Bitcoin
- Solana
- XRP Ledger
- Tron

Each network tracks:

- family
- chain ID where applicable
- native asset
- deposit/withdrawal enabled state
- required confirmations
- finality confirmations
- memo/tag requirements
- operational state

## Provider Layer

`BlockchainProviderInterface` exposes health, block, transaction, address validation, fee, broadcast, and balance methods. Provider failover can be added without changing deposit or withdrawal services.

The current code ships with a development provider for local/testing. Production providers must be configured with real RPC/indexer infrastructure and audited credentials.

## Chain-Specific Controls

- EVM uses `BlockchainNonceService` for nonce reservations.
- Bitcoin uses `BitcoinUtxoService` for UTXO selection and reservation.
- XRP requires destination tags for deposit assignment.
- Solana and Tron are represented as network families with finality/resource policy fields ready for provider implementation.
