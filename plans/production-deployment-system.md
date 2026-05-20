# ExaEarn Production Deployment System

This is the go-live control plan for ExaEarn. Mainnet is blocked until testnet deployment, verification, security checks, and operational monitoring are complete.

## Phase 1: Testnet Deployment

Target networks:
- Base Sepolia: primary testnet
- Ethereum Sepolia: Ethereum compatibility validation
- BSC Testnet: BNB Chain compatibility validation

Commands:
```bash
cd exaearn-node
npm run contracts:compile
npm run contracts:test
npm run contracts:deploy:base-sepolia
npm run contracts:verify:base-sepolia
npm run contracts:deploy:sepolia
npm run contracts:verify:sepolia
npm run contracts:deploy:bsc-testnet
npm run contracts:verify:bsc-testnet
```

Deployment outputs are written to `exaearn-node/deployments/<network>.json`. These records are the source of truth for backend and node service environment updates.

Test before mainnet:
- staking: stake, unstake, reward accrual, unauthorized calls
- lottery: create game, join game, draw winner, edge cases
- NFT minting: mint, listing, fee routing, unauthorized mint attempts
- reward distribution: role checks, emission limits, repeated claims
- wallet transactions: deposit detection, withdrawal signing, failed broadcast handling
- multi-user flows: concurrent users, repeated requests, insufficient balances

Token emission rule:
- `ExaToken` keeps a 1,000,000,000 EXA max supply
- initial treasury mint is 900,000,000 EXA
- remaining supply is reserved for controlled rewards and staking emissions

## Phase 2: Security Hardening

Application layer:
- validate all request payloads
- enforce rate limiting on auth, wallet, withdrawal, admin, giftcard, and contract-write endpoints
- restrict admin routes with permissions and 2FA
- keep secrets out of frontend builds and git
- enable HTTPS-only cookies and secure CORS in production

Blockchain layer:
- use OpenZeppelin access control and ownership patterns
- keep reentrancy protection on fund-moving functions
- verify role assignments after deployment
- never deploy with a browser wallet or hot wallet holding treasury funds
- use separate deployer, admin, treasury, bridge operator, and reward operator addresses

Audit checklist:
- funds cannot be drained by non-admin users
- rewards cannot be minted or claimed twice
- lottery cannot be manipulated by caller ordering
- staking cannot bypass lock rules
- bridge operator cannot mint without operational controls
- every privileged function has an owner or role check

## Phase 3: Backend Production Deployment

Laravel target:
- VPS or cloud instance behind Nginx
- PostgreSQL production database
- Redis for cache, queues, rate limit state, and streams
- HTTPS certificate through Let's Encrypt or managed TLS

Minimum commands on server:
```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan queue:restart
```

Required environment:
- `APP_ENV=production`
- `APP_DEBUG=false`
- production `APP_KEY`
- production database credentials
- Redis credentials
- `NODE_SERVICE_URL`
- `NODE_SERVICE_SECRET`
- verified contract addresses from deployment records

## Phase 4: Node Blockchain Service Deployment

Use PM2 or systemd with restricted environment access.

```bash
cd exaearn-node
npm ci --omit=dev
pm2 start src/index.js --name exaearn-node
pm2 save
```

Operational rules:
- private keys live only in server secrets or vault storage
- RPC URLs use paid or reliable providers for production
- signer wallets keep minimum hot-wallet balances
- withdrawal limits and anomaly thresholds stay enabled
- logs are rotated and monitored

## Phase 5: Frontend Deployment

Deploy `exaearn-frontend` to Vercel, Netlify, or a static host.

Required frontend environment:
- `VITE_API_URL=https://api.example.com`
- `VITE_NODE_SERVICE_URL=https://node.example.com`
- optional `VITE_GOOGLE_CLIENT_ID`

Before launch:
- run `npm run build`
- test registration, login, wallet pages, giftcard flows, dashboard, and mobile breakpoints
- confirm no private RPC URLs or private keys appear in built assets

## Phase 6: Mainnet Strategy

Mainnet deployment is allowed only when:
- all testnet contracts are verified
- all critical tests pass
- internal audit findings are resolved
- backend and node service are stable under staging load
- monitoring and rollback procedures are ready
- treasury policy is approved

Mainnet commands:
```bash
cd exaearn-node
npm run contracts:deploy:base
npm run contracts:verify:base
```

Optional networks:
```bash
npm run contracts:deploy:ethereum
npm run contracts:verify:ethereum
npm run contracts:deploy:bsc
npm run contracts:verify:bsc
```

Hybrid launch rule:
- keep ExaPoints off-chain at first
- use blockchain only for flows that need public settlement
- cap hot-wallet exposure
- raise limits gradually after stable production history

## Phase 7: Monitoring

Track:
- failed API requests
- wallet balance changes
- contract events
- withdrawal broadcasts
- queue failures
- fraud flags
- admin actions
- server CPU, memory, disk, database load

Alert on:
- failed withdrawal broadcasts
- unusual withdrawal volume
- hot wallet low balance
- repeated failed login or admin attempts
- queue backlog
- RPC provider failures

## Phase 8: Treasury And Fund Safety

Minimum controls:
- cold wallet stores reserves
- hot wallet has strict maximum balance
- treasury and admin addresses use multisig before serious mainnet volume
- deployer key is retired or permissions are transferred after deployment
- emergency pause/disable procedures are documented for every fund-moving module

## Final Launch Checklist

- [ ] contracts compile
- [ ] contract tests pass
- [ ] Base Sepolia deployment complete
- [ ] Ethereum Sepolia deployment complete
- [ ] BSC Testnet deployment complete
- [ ] all testnet contracts verified
- [ ] backend deployed with production env
- [ ] database migrations applied
- [ ] queues and Redis running
- [ ] node blockchain service running under PM2
- [ ] frontend deployed and pointed at production API
- [ ] monitoring and alerts active
- [ ] treasury hot/cold policy active
- [ ] internal audit completed
- [ ] no critical or high severity bugs open
- [ ] mainnet deployer wallet funded only with required gas
- [ ] mainnet deployment approved
