# Security Policy

## Secrets

Never commit real secrets, private keys, wallet mnemonics, production API keys, database passwords, or `.env` files.

Use the committed `.env.example` files as templates only:
- `.env.example`
- `exaearn-backend/.env.example`
- `exaearn-frontend/.env.example`
- `exaearn-node/.env.example`

Production secrets must live in the deployment platform, server environment, or a secret manager.

## Blockchain Safety

- Do not use production private keys in local development.
- Keep deployer, admin, treasury, bridge operator, and hot wallet keys separate.
- Use multisig wallets for treasury and admin roles before handling meaningful mainnet value.
- Keep hot wallet balances capped.
- Rotate keys immediately if a secret is exposed.

## Deployment Safety

- Run tests before deployment.
- Verify contracts on explorers before public launch.
- Keep `APP_DEBUG=false` in production.
- Enable HTTPS, secure cookies, rate limiting, and monitoring.
- Do not deploy generated folders such as `node_modules`, `vendor`, `dist`, `cache`, `artifacts`, logs, or local databases.
