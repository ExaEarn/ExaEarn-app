# ExaEarn Giftcards Provider Integration

Providers must implement `App\Services\GiftCard\GiftCardProviderInterface`.

Required methods:

- `purchase(array $payload): array`
- `checkOrder(string $providerReference): array`
- `refund(string $providerReference, array $payload = []): array`
- `balance(string $currency): array`
- `name(): string`

## Status Handling

- `SUCCESS`: settle reservation and deliver.
- `FAILED` / `OUT_OF_STOCK`: do not create a completed settlement.
- `PROVIDER_UNKNOWN`: keep the reservation active and move the order to `provider_unknown` for reconciliation/manual review.

## Sandbox Adapter

`FakeGiftCardProvider` exists for development/testing only. In production, `GiftCardProviderManager` blocks fake-provider usage unless explicitly enabled by configuration for a controlled sandbox deployment.

