# ExaEarn NFT Media Operations

Admin NFT media operations expose:

- media inventory
- processing state
- quarantine/restriction/removal actions
- storage health
- media reconciliation
- moderation report review

Reconciliation detects:

- missing objects
- private media exposed publicly
- NFTs referencing unready media

Operational alerts should monitor upload failure spikes, quarantine spikes, missing objects and metadata/media mismatch events through the existing Phase 19 observability path.

