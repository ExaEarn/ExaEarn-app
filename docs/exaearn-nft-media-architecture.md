# ExaEarn NFT Media Architecture

NFT media is a read/display and metadata integrity layer around the existing NFT marketplace. It does not change NFT ownership, settlement, royalties, bids or ledger behavior.

Flow:

User upload -> `NftMediaService` validation -> `NftMediaStorageProviderInterface` -> `nft_media_assets` -> NFT metadata/media reference -> web/mobile display.

The canonical database row stores storage references, hashes, MIME, size, visibility and processing state. The actual object lives in the configured storage provider.

Public marketplace previews use public media. Report evidence and sensitive moderation files use private media and authorized URL access.

