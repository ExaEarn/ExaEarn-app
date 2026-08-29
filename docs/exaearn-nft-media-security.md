# ExaEarn NFT Media Security

Server-side upload checks include:

- media category validation
- MIME allowlist
- extension allowlist
- size limits by category
- executable/script extension rejection
- malformed image rejection where PHP image metadata support is available
- production provider fail-closed behavior

Private media is not assigned a public URI. Report evidence remains private and is available only through authorized access.

Remote imports and IPFS gateways should be added through the same provider abstraction with SSRF and timeout protections before public enablement.

