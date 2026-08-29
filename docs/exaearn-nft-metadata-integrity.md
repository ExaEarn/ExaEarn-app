# ExaEarn NFT Metadata Integrity

Each uploaded media object stores:

- `checksum`
- `content_hash`
- `metadata_hash`
- `metadata_version`
- `metadata_uri`

NFT metadata references canonical media only after upload validation succeeds. Failed or unavailable media is not promoted as ready metadata.

Immutable finalized media cannot be silently removed. Mutable metadata must create an auditable versioned update rather than replacing a committed hash without history.

