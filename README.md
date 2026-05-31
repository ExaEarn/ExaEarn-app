# ExaEarn

ExaEarn is a modular Web3 infrastructure platform that connects decentralized finance with real-world economic utility. The platform runs a unified token and ledger system where every financial action is validated, recorded, and auditable.

## Vision
Close the utility gap in Web3 by connecting blockchain technology to real-world industries, payments, and economic systems, starting with emerging markets.

## Core Focus Areas
- Real-world staking infrastructure
- AgriTech-backed digital yield systems
- Giftcard liquidity rails
- AI-powered earning optimization
- Transparent on-chain reward mechanisms

## Technology Stack
### Frontend
- `apps/website` - React/Vite marketing website
- `apps/web` - React/Vite user dashboard
- `apps/admin` - React/Vite admin console
- TypeScript-ready shared configuration
- Tailwind CSS
- Axios or Fetch for API communication

### Backend
- `backend/api-gateway` Laravel Transaction Service
- `backend/services/*` domain services
- PHP 8.2+
- REST API
- Laravel Jobs + Redis queues

### Database
- PostgreSQL
- Redis for queues and caching

### Blockchain Monitoring
- Node.js
- Ethers.js
- Web3.js
- Network: Base

## Transaction Engine
The Transaction Engine is the financial heart of ExaEarn. Every financial action must flow through this engine to guarantee accurate accounting, auditability, and security. No service is allowed to modify balances directly outside the engine.

### Responsibilities
- Validate financial actions
- Update wallet balances
- Record transaction history
- Prevent double spending
- Maintain financial audit logs
- Interact with blockchain services
- Process background jobs via queues

### Supported Transaction Types
- deposit
- withdrawal
- internal_transfer
- trade
- staking_lock
- staking_reward
- nft_purchase
- lottery_bet
- lottery_reward
- referral_reward
- platform_reward

Each transaction has a globally unique `transaction_id`.

### Frontend Capabilities
- React application shell and routing
- Tailwind CSS styling
- Axios or Fetch API communication
- Zustand and context-driven state where needed

## Transaction Flow Examples
### User Deposit
External exchange
-> Blockchain transaction
-> Node.js detects transaction
-> Node.js sends webhook to Laravel
-> Transaction Engine verifies deposit
-> Wallet balance updated
-> Transaction recorded

### Internal Transfer
User A sends EXA to User B
- Validate sender balance
- Create transaction record
- Debit sender wallet
- Credit receiver wallet
- Log audit record

### Withdrawal
- User submits withdrawal request
- Validate balance
- Verify 2FA
- Queue withdrawal job
- Node.js signs transaction
- Broadcast to blockchain
- Update transaction status

### Blockchain And Web3
- Solidity smart contracts on EVM networks
- Hardhat contract development and tests
- Ethers.js and Web3.js blockchain interaction
- XRP Ledger SDK integration
- Wallet connection support

## Database Design (PostgreSQL)
### transactions
Core ledger table.
- id
- transaction_id (unique)
- user_id
- type
- currency
- amount
- fee
- status
- reference
- tx_hash
- metadata
- created_at
- updated_at

Status values:
- pending
- processing
- completed
- failed
- cancelled

### wallets
Stores user balances.
- id
- user_id
- currency
- available_balance
- locked_balance
- created_at
- updated_at

### wallet_transactions
Links transactions to wallet balance updates.
- id
- wallet_id
- transaction_id
- amount
- balance_before
- balance_after
- created_at

### audit_logs
Records financial events.
- id
- user_id
- action
- ip_address
- metadata
- created_at

## Queue Processing System
Heavy financial operations run through queues.
- Laravel Jobs
- Redis queue driver

Example jobs:
- ProcessDepositJob
- ProcessWithdrawalJob
- DistributeRewardJob
- ExecuteTradeJob
- FinalizeLotteryJob

## Blockchain Verification Layer
The Node.js service verifies on-chain transactions and broadcasts withdrawals.

Process:
Node.js detects blockchain event
-> Send secure webhook to Laravel
-> Laravel Transaction Engine verifies data
-> Credit user wallet

Webhook security:
- Use `X-Webhook-Token`
- Compare against `NODE_WEBHOOK_SECRET` from `.env`

## Security Requirements
- Immutable ledger: completed transactions should never be edited or deleted
- Atomic database transactions for all balance updates
- Sensitive configuration in `.env`
- Anti-fraud controls (limits, rate limiting, duplicate detection, IP monitoring)

## Backend Structure (Laravel)
- `app/Services/TransactionService.php`
- `app/Services/DepositService.php`
- `app/Services/WithdrawalService.php`
- `app/Services/TransferService.php`
- `app/Repositories/TransactionRepository.php`
- `app/Repositories/WalletRepository.php`
- `app/Models/Transaction.php`
- `app/Models/Wallet.php`
- `app/Models/WalletTransaction.php`
- `app/Models/AuditLog.php`
- `app/Jobs/*`
- `routes/api.php`

## API Endpoints
Base: `/api`
- `GET /transactions` -> list all transactions (admin)
- `GET /transactions/{id}` -> transaction detail
- `GET /transactions/user?user_id=` -> user transaction history
- `POST /transactions/transfer` -> internal transfer
- `POST /transactions/withdraw` -> withdrawal request
- `POST /transactions/deposit/webhook` -> deposit webhook (Node.js)

## Frontend Integration
Frontend uses `VITE_API_URL` to communicate with the backend API. The Transactions screen should call the API and map the response into the UI model.

Suggested flow:
- Set `.env`: `VITE_API_URL=http://localhost:8000`
- Call `GET /api/transactions/user?user_id=<id>`
- Render the list in `src/pages/Transactions/Transactions.jsx`
- Use the returned `transaction_id`, `type`, `amount`, `currency`, `status`, and `created_at`

## Local Development
Backend:
- `cd backend/api-gateway`
- `composer install`
- `php artisan migrate`
- `php artisan serve`
- `php artisan queue:listen`

Frontend monorepo:
- `pnpm install`
- `pnpm web:dev`
- `pnpm admin:dev`
- `pnpm website:dev`

Direct app fallback:
- `pnpm --filter @exaearn/web dev`

## Roadmap 2026
### Q1
- MVP launch
- Wallet integration
- Initial user acquisition

### Q2
- XRP staking module
- Giftcard integration
- Community expansion

### Q3
- Smart contract audit
- AgriTech yield pilot

### Q4
- Governance framework
- Tokenomics release
- 50,000+ users target

## Contributing
See `CONTRIBUTING.md`.

## Manifesto
See `MANIFESTO.md`.

## Community
Discord: https://discord.gg/FC7Dgq3V
Twitter/X: https://x.com/exaearn9470

ExaEarn - Building real economic infrastructure for Web3.
