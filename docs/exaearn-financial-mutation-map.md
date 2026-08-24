# ExaEarn Financial Mutation Map

Phase: 1
Date: 2026-08-14
Source audit: docs/exaearn-trading-infrastructure-audit.md

This map records financially relevant mutation paths found during Phase 1. It is not a deletion list. Legacy tables remain in place until migration, reconciliation, and compatibility work are complete.

| File | Method / path | Table / model mutated | Reason | Ledger-backed after Phase 1? | Must migrate? |
| --- | --- | --- | --- | --- | --- |
| backend/api-gateway/app/Services/LedgerService.php | commitTransaction, postDoubleEntry, internalTransfer | accounts, ledger_transactions, ledger_entries | Canonical double-entry posting and account balance projection | Yes | No; canonical foundation |
| backend/api-gateway/app/Services/LedgerService.php | rollbackTransaction | ledger_transactions, ledger_entries | Legacy rollback path previously deleted entries | Reworked to reversal transaction | Keep deprecated; use LedgerReversalService |
| backend/api-gateway/app/Services/TransferService.php | internalTransfer | accounts, ledger_entries, reservations, wallet_balances, internal_wallet_transactions | User internal wallet transfer | Yes for money movement; legacy wallet_balances updated as compatibility projection | Projection-only legacy sync should later move to event/materialized projection |
| backend/api-gateway/app/Services/TransferService.php | transfer | transactions/wallet abstractions via TransactionService | Peer/internal user transfer | No | Yes |
| backend/api-gateway/app/Services/TransactionService.php | recordDeposit, recordWithdrawal, recordInternalTransfer, applyWalletEffect | wallets, wallet_transactions, transactions | Legacy wallet transaction lifecycle | Partial; still wallet-authoritative for several flows | Yes |
| backend/api-gateway/app/Services/WalletService.php | credit, debit, lock, release, transfer helpers | wallets, balances, accounts, ledger_entries | Legacy wallet accounting and compatibility ledger entries | Partial; precision helper improved, but still directly mutates balances | Yes |
| backend/api-gateway/app/Services/UnifiedTradingAccountService.php | transfer and balance aggregation paths | wallet_balances, wallets, internal_accounts | Funding/unified trading transfer and display | Existing tests pass; still legacy-authoritative | Yes |
| backend/api-gateway/app/Services/UnifiedTradingReservationService.php | reserve, release, consume | wallets, internal_accounts | Spot/futures local reservation buckets | No; canonical ReservationService exists separately | Yes; futures/spot should move to ReservationService |
| backend/api-gateway/app/Services/TradeService.php | placeOrder, matchOrder, cancelOrder, settleTrade | wallets, orders, trades | Development spot order placement/matching/settlement | SettlementService supports spot settlement, but TradeService still has direct legacy balance mutations | Yes before Phase 2 |
| backend/api-gateway/app/Services/SpotTradingService.php | lockBalance, unlockBalance | balances.spot_available, balances.spot_locked | Spot reservation buckets | No | Yes |
| backend/api-gateway/app/Services/FuturesOrderService.php | create/cancel order margin reservation | internal_accounts through UnifiedTradingReservationService | Futures initial margin/order reservations | No; canonical reservation service added but not fully wired | Yes before futures production migration |
| backend/api-gateway/app/Services/FuturesPositionService.php | position open/close/accounting helpers | futures_positions and derived account values | Futures position accounting | Partial; still product-specific | Yes in futures phase |
| backend/api-gateway/app/Services/FuturesLiquidationService.php | liquidation release/adjustment | internal_accounts | Futures liquidation/account release | No | Yes in futures phase |
| backend/api-gateway/app/Services/SwapEngineService.php | execute, complete, fail | wallets, quotes, swaps via WalletService | Convert/swap funds lock/debit/credit | SettlementService supports convert settlement, but active engine remains legacy | Yes |
| backend/api-gateway/app/Services/FiatWithdrawalIntentService.php | create intent, reserve, submit, reverse | fiat withdrawal intents, ledger/account bridge | Fiat withdrawal intent lifecycle | Partially ledger-backed already, but contains float fallback debt | Yes for unified reservation semantics |
| backend/api-gateway/app/Services/WithdrawalEngineService.php | reserve/release/settle | wallets | Crypto withdrawal reservation/debit lifecycle | No | Yes |
| backend/api-gateway/app/Services/DepositService.php | confirmed deposit credit | transactions/wallet service | Deposit credit after confirmation | Partial through legacy transaction/wallet path | Yes |
| backend/api-gateway/app/Services/FeeTreasuryService.php | record, reserve, release | treasury accounts/balances plus ledger | Fee collection/treasury | Partial; direct treasury balances remain | Yes for canonical treasury projections |
| backend/api-gateway/app/Services/TreasuryService.php | credit, debit, reserve, release | treasury_accounts, treasury_transactions | Platform treasury/custody accounting | No, custody balance model remains separate | Yes where it represents accounting, not custody metadata |
| backend/api-gateway/app/Services/CryptoTreasuryService.php | credit, debit, moveHotToCold, moveColdToHot | treasury_balances | Hot/cold custody balance tracking | Custody metadata only; not user liability ledger | Keep as custody projection, reconcile against canonical treasury accounts |
| backend/api-gateway/app/Services/P2PService.php | createAd, lock/release escrow, complete trade | wallets, p2p_ads, p2p_trades | P2P ad reservation/order settlement | No | Yes in P2P escrow phase |
| backend/api-gateway/app/Services/GiftCard/GiftCardPurchaseService.php | purchase/refund | wallets | Giftcard purchase/refund | No | Yes |
| backend/api-gateway/app/Services/GiftCard/GiftCardSellService.php | payout | wallets | Giftcard sell payout | No | Yes |
| backend/api-gateway/app/Services/ExaSkillsService.php | course/challenge commerce helpers | skills transactions/wallet-related commerce | Skills commerce and payouts | No; has float fallback debt | Yes before production commerce |
| backend/api-gateway/app/Services/AgriService.php | investment/reward save paths | agritech project/share/reward records | Agritech investments/rewards | No | Yes if real-money enabled |
| backend/api-gateway/app/Services/ExaAiService.php | allocation/performance helpers | ExaAI financial records | ExaAI allocation/performance | No; has float fallback debt | Yes before live automated trading |
| backend/api-gateway/app/Http/Controllers/WithdrawalCenterController.php | internal transfer/send/withdraw helpers | balances.funding_available, spot_available, futures_available | Legacy withdrawal/send flow | No | Yes |
| backend/api-gateway/app/Http/Controllers/Admin/AdminPlatformController.php | freeze/unfreeze/adjust wallet | wallets.available_balance, locked_balance | Admin wallet controls | No; must become audited ledger adjustment/reservation | Yes, high priority |
| backend/api-gateway/app/Http/Controllers/Admin/AdminSettingController.php | wallet settings save | treasury/admin wallet settings | Admin configuration | Not a user-money movement unless balance fields touched | Review |
| backend/api-gateway/app/Models/Wallet.php | getTotalBalanceAttribute | wallets available + locked | Display helper | Precision fixed to BCMath-only FinancialDecimal | No immediate migration, still legacy projection |

## Notes

- Phase 1 added canonical services and migrated `TransferService::internalTransfer` money movement to the canonical ledger with legacy `wallet_balances` kept as compatibility projection.
- `TradeService`, `SwapEngineService`, `UnifiedTradingReservationService`, futures reservation paths, P2P escrow, giftcard, staking, ExaSkills, Agri and ExaAI still contain direct or indirect legacy balance writes and must not be considered production-safe accounting paths yet.
- Tests and factories intentionally write balances for setup. They are not production money paths, but invariant tests should expand to catch forbidden production writes as each module migrates.