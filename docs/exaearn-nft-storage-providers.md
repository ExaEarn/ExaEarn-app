# ExaEarn NFT Storage Providers

The provider contract is `App\Contracts\NftMediaStorageProviderInterface`.

Required methods:

- `upload`
- `delete`
- `exists`
- `getPublicUrl`
- `getSignedUrl`
- `getMetadata`
- `health`

Current provider:

- `NftLocalMediaStorageProvider`

Modes:

- `LOCAL_TEST`
- `SANDBOX`
- `PRODUCTION`

In `PRODUCTION`, uploads fail closed unless real storage is explicitly configured.

Future providers can implement the same contract for S3, R2, IPFS-compatible storage or CDN-backed object storage without changing NFT marketplace financial logic.

