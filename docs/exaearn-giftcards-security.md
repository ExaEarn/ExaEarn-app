# ExaEarn Giftcards Security

Security posture:

- direct wallet mutation removed from Giftcard services
- sandbox provider blocked in production unless explicitly enabled
- provider unknown state does not mark success
- full card details are delivered through secure in-app reveal
- notifications avoid exposing raw card codes
- fraud/risk review remains in the existing Giftcard risk pipeline
- admin approval continues to use the existing admin queue flow

Full card detail access remains logged with user, order, card, and request IP context.

