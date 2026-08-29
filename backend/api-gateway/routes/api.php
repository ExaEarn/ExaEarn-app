<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\P2POperationsController;
use App\Http\Controllers\AgriController;
use App\Http\Controllers\AffiliateController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ExaPointController;
use App\Http\Controllers\ExaCardController;
use App\Http\Controllers\ExaCardWebhookController;
use App\Http\Controllers\EventStreamController;
use App\Http\Controllers\FuturesController;
use App\Http\Controllers\FlightGameController;
use App\Http\Controllers\GameFiController;
use App\Http\Controllers\GiftcardController;
use App\Http\Controllers\NftController;
use App\Http\Controllers\P2PController;
use App\Http\Controllers\ReferralController;
use App\Http\Controllers\RewardController;
use App\Http\Controllers\SportsController;
use App\Http\Controllers\StakingController;
use App\Http\Controllers\SwapController;
use App\Http\Controllers\TradeController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserPreferenceController;
use App\Http\Controllers\PersonalizedContentController;
use App\Http\Controllers\WalletController;
use App\Http\Controllers\CustodyController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\WithdrawalCenterController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\MarginController;
use App\Http\Controllers\FiatWithdrawalController;
use App\Http\Controllers\FiatController;
use App\Http\Controllers\ExaPayMerchantController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\SupportController;
use App\Http\Controllers\SupportLiveChatController;
use App\Http\Controllers\CrowdfundingController;
use App\Http\Controllers\UnifiedActivityCenterController;
use App\Http\Controllers\ProfileIdentityController;
use App\Http\Controllers\PricingController;
use App\Http\Controllers\KycController;
use App\Http\Controllers\Admin\KycAdminController;
use App\Http\Controllers\Admin\AIIntelligenceController;
use App\Http\Controllers\Admin\MarketMakerAdminController;
use App\Http\Controllers\Admin\SmartOrderRoutingAdminController;
use App\Http\Controllers\Admin\TreasuryController;
use App\Http\Controllers\Admin\TreasuryMonitoringController;
use App\Http\Controllers\Admin\AdminGiftCardBuyController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminPlatformController;
use App\Http\Controllers\Admin\SecurityController;
use App\Http\Controllers\GiftCardBuyController;
use App\Http\Controllers\Admin\GiftCardAdminController;
use App\Http\Controllers\Admin\ExaAiAdminController;
use App\Http\Controllers\Admin\ExaSkillsAdminController;
use App\Http\Controllers\Admin\StakingAdminController;
use App\Http\Controllers\Admin\TradingOperationsController;
use App\Http\Controllers\Admin\LiquidityOperationsController;
use App\Http\Controllers\Admin\CustodyOperationsController;
use App\Http\Controllers\Admin\FiatOperationsController;
use App\Http\Controllers\Admin\ExaPayOperationsController;
use App\Http\Controllers\Admin\ListingCenterController;
use App\Http\Controllers\Admin\InstitutionalOperationsController;
use App\Http\Controllers\Admin\CopyTradingOperationsController;
use App\Http\Controllers\Admin\NotificationOperationsController;
use App\Http\Controllers\Admin\NftOperationsController;
use App\Http\Controllers\Admin\SupportOperationsController;
use App\Http\Controllers\Admin\SupportLiveChatOperationsController;
use App\Http\Controllers\Admin\CrowdfundingOperationsController;
use App\Http\Controllers\Admin\MarketMakerOperationsController;
use App\Http\Controllers\Admin\MarketMakerBotOperationsController;
use App\Http\Controllers\Admin\OtcOperationsController;
use App\Http\Controllers\Admin\Phase15OperationsController;
use App\Http\Controllers\Admin\ComplianceController;
use App\Http\Controllers\Admin\FinanceController;
use App\Http\Controllers\Admin\SecurityOperationsController;
use App\Http\Controllers\Admin\ReliabilityOperationsController;
use App\Http\Controllers\Admin\PricingRewardsController;
use App\Http\Controllers\Admin\ExaCardOperationsController;
use App\Http\Controllers\Admin\AgriTechOperationsController;
use App\Http\Controllers\Admin\AffiliateOperationsController;
use App\Http\Controllers\Admin\FlightGameAdminController;
use App\Http\Controllers\Admin\PersonalizedContentAdminController;
use App\Http\Controllers\API\AITradingAssistantController;
use App\Http\Controllers\API\ExaAiController;
use App\Http\Controllers\API\ExaSkillsController;
use App\Http\Controllers\Developer\DeveloperApiController;
use App\Http\Controllers\Developer\DeveloperPortalController;
use App\Http\Controllers\InstitutionalController;
use App\Http\Controllers\ListingPortalController;
use App\Http\Controllers\MarketMakerController;
use App\Http\Controllers\MarketMakerBotController;
use App\Http\Controllers\OtcController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BlockchainEventController;
use App\Http\Controllers\CopyTradingController;
use App\Http\Controllers\EligibilityController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::prefix('v1/market')->middleware('throttle:120,1')->group(function (): void {
    Route::get('symbols', [TradeController::class, 'symbols']);
    Route::get('summary', [TradeController::class, 'summary']);
    Route::get('tickers', [TradeController::class, 'tickers']);
    Route::get('ticker/{symbol}', [TradeController::class, 'ticker']);
    Route::get('order-book/{symbol}', [TradeController::class, 'orderBook']);
    Route::get('order-book', [TradeController::class, 'orderBookByQuery']);
    Route::get('trades/{symbol}', [TradeController::class, 'trades']);
    Route::get('trades', [TradeController::class, 'tradesByQuery']);
    Route::get('klines/{symbol}', [TradeController::class, 'candles']);
    Route::get('klines', [TradeController::class, 'klines']);
    Route::get('deltas/{symbol}', [TradeController::class, 'marketDeltas']);
    Route::get('stream/snapshot', [TradeController::class, 'marketStreamSnapshot']);
    Route::get('health', [TradeController::class, 'marketDataHealth']);
});

Route::prefix('v1')->middleware('throttle:120,1')->group(function (): void {
    Route::get('markets', [TradeController::class, 'summary']);
    Route::get('ticker', [TradeController::class, 'tickers']);
    Route::get('ticker/24hr', [TradeController::class, 'summary']);
    Route::get('orderbook', [TradeController::class, 'orderBookByQuery']);
    Route::get('orderbook/{symbol}', [TradeController::class, 'orderBook']);
    Route::get('trades', [TradeController::class, 'tradesByQuery']);
    Route::get('trades/{symbol}', [TradeController::class, 'trades']);
});

Route::prefix('developer/v1')->middleware(['developer.context', 'throttle:240,1'])->group(function (): void {
    Route::get('exchange-info', [DeveloperApiController::class, 'exchangeInfo']);
    Route::get('status', [DeveloperApiController::class, 'apiStatus']);
    Route::get('markets', [DeveloperApiController::class, 'markets']);
    Route::get('tickers', [DeveloperApiController::class, 'tickers']);
    Route::get('ticker/{symbol}', [DeveloperApiController::class, 'ticker']);
    Route::get('orderbook/{symbol}', [DeveloperApiController::class, 'orderBook']);
    Route::get('trades/{symbol}', [DeveloperApiController::class, 'trades']);
    Route::get('klines/{symbol}', [DeveloperApiController::class, 'klines']);

    Route::middleware('developer.api:account.read')->group(function (): void {
        Route::get('wallet/balances', [DeveloperApiController::class, 'balances']);
        Route::post('realtime/session', [DeveloperApiController::class, 'realtimeSession']);
        Route::get('realtime/replay', [DeveloperApiController::class, 'realtimeReplay']);
    });
    Route::middleware('developer.api:futures.read')->group(function (): void {
        Route::get('futures/markets', [DeveloperApiController::class, 'futuresMarkets']);
        Route::get('futures/open-orders', [FuturesController::class, 'openOrders']);
        Route::get('futures/orders', [DeveloperApiController::class, 'futuresOrders']);
        Route::get('futures/orders/{orderUuid}', [FuturesController::class, 'orderDetails']);
        Route::get('futures/positions', [DeveloperApiController::class, 'futuresPositions']);
        Route::get('futures/trades', [FuturesController::class, 'trades']);
        Route::get('futures/margin/status', [FuturesController::class, 'marginStatus']);
    });
    Route::middleware('developer.api:futures.trade')->group(function (): void {
        Route::post('futures/orders/validate', [FuturesController::class, 'validateOrder']);
        Route::post('futures/orders', [FuturesController::class, 'placeOrder'])->middleware('rate.limit');
        Route::post('futures/orders/conditional', [FuturesController::class, 'createConditionalOrder'])->middleware('rate.limit');
        Route::post('futures/orders/batch-cancel', [FuturesController::class, 'batchCancelOrders'])->middleware('rate.limit');
        Route::delete('futures/orders/{orderUuid}', [FuturesController::class, 'cancelOrder'])->middleware('rate.limit');
        Route::post('futures/margin/type', [FuturesController::class, 'setMarginType'])->middleware('rate.limit');
    });
    Route::middleware('developer.api:margin.read')->group(function (): void {
        Route::get('margin/overview', [MarginController::class, 'overview']);
        Route::get('margin/accounts', [DeveloperApiController::class, 'marginAccounts']);
        Route::get('margin/assets', [MarginController::class, 'assets']);
        Route::get('margin/pools', [MarginController::class, 'pools']);
        Route::get('margin/health', [MarginController::class, 'health']);
        Route::get('margin/loans', [DeveloperApiController::class, 'marginLoans']);
        Route::get('margin/orders', [MarginController::class, 'orders']);
        Route::get('margin/interest', [MarginController::class, 'interest']);
        Route::get('margin/realtime/snapshot', [MarginController::class, 'realtimeSnapshot']);
    });
    Route::middleware('developer.api:margin.manage')->group(function (): void {
        Route::post('margin/accounts', [MarginController::class, 'createAccount'])->middleware('rate.limit');
        Route::post('margin/transfer', [MarginController::class, 'transfer'])->middleware('rate.limit');
        Route::post('margin/borrow', [MarginController::class, 'borrow'])->middleware('rate.limit');
        Route::post('margin/loans/{loanUuid}/repay', [MarginController::class, 'repay'])->middleware('rate.limit');
        Route::post('margin/orders', [MarginController::class, 'placeOrder'])->middleware('rate.limit');
        Route::post('margin/orders/{marginOrderUuid}/cancel', [MarginController::class, 'cancelOrder'])->middleware('rate.limit');
    });
    Route::middleware('developer.api:convert.read')->group(function (): void {
        Route::get('convert/meta', [SwapController::class, 'meta']);
        Route::get('convert/history', [DeveloperApiController::class, 'convertHistory']);
        Route::get('convert/{swapId}', [SwapController::class, 'show']);
    });
    Route::middleware('developer.api:convert.execute')->group(function (): void {
        Route::post('convert/quote', [SwapController::class, 'quote']);
        Route::post('convert/execute', [SwapController::class, 'execute'])->middleware('rate.limit');
    });
    Route::middleware('developer.api:exapay.read')->group(function (): void {
        Route::get('exapay/merchants', [ExaPayMerchantController::class, 'merchants']);
        Route::get('exapay/merchants/{merchantId}/overview', [ExaPayMerchantController::class, 'overview']);
        Route::get('exapay/merchants/{merchantId}/payments', [ExaPayMerchantController::class, 'payments']);
        Route::get('exapay/merchants/{merchantId}/payment-links', [ExaPayMerchantController::class, 'links']);
        Route::get('exapay/merchants/{merchantId}/reconciliation', [ExaPayMerchantController::class, 'reconciliation']);
    });
    Route::middleware('developer.api:exapay.write')->group(function (): void {
        Route::post('exapay/merchants/{merchantId}/payment-intents', [ExaPayMerchantController::class, 'createIntent'])->middleware('rate.limit');
        Route::post('exapay/payment-intents/{payIntent}/capture', [ExaPayMerchantController::class, 'capture'])->middleware('rate.limit');
        Route::post('exapay/merchants/{merchantId}/payment-links', [ExaPayMerchantController::class, 'createLink'])->middleware('rate.limit');
    });
    Route::middleware('developer.api:exapay.refunds')->group(function (): void {
        Route::post('exapay/merchants/{merchantId}/refunds', [ExaPayMerchantController::class, 'refund'])->middleware('rate.limit');
    });
    Route::middleware('developer.api:staking.read')->group(function (): void {
        Route::get('staking/assets', [StakingController::class, 'assets']);
        Route::get('staking/products', [StakingController::class, 'products']);
        Route::get('staking/products/{slug}', [StakingController::class, 'product']);
        Route::get('staking/portfolio', [StakingController::class, 'portfolio']);
        Route::get('staking/positions', [StakingController::class, 'positions']);
        Route::get('staking/positions/{publicId}', [StakingController::class, 'showPosition']);
        Route::get('staking/rewards', [StakingController::class, 'rewards']);
        Route::get('staking/transactions', [StakingController::class, 'transactions']);
    });
    Route::middleware('developer.api:staking.manage')->group(function (): void {
        Route::post('staking/terms/accept', [StakingController::class, 'acceptTerms'])->middleware('rate.limit');
        Route::post('staking/positions', [StakingController::class, 'createPosition'])->middleware('rate.limit');
        Route::post('staking/positions/{publicId}/unstake', [StakingController::class, 'unstake'])->middleware('rate.limit');
        Route::post('staking/positions/{publicId}/claim-native-rewards', [StakingController::class, 'claimNativeRewards'])->middleware('rate.limit');
        Route::post('staking/positions/{publicId}/claim-exatoken-rewards', [StakingController::class, 'claimExaTokenRewards'])->middleware('rate.limit');
        Route::patch('staking/positions/{publicId}/auto-compound', [StakingController::class, 'updateAutoCompound'])->middleware('rate.limit');
    });
    Route::middleware('developer.api:copy.read')->group(function (): void {
        Route::get('copy/eligibility', [CopyTradingController::class, 'eligibility']);
        Route::get('copy/leaders', [CopyTradingController::class, 'leaders']);
        Route::get('copy/leaders/{id}', [CopyTradingController::class, 'leader']);
        Route::get('copy/relationships', [CopyTradingController::class, 'relationships']);
        Route::get('copy/orders', [CopyTradingController::class, 'orders']);
        Route::get('copy/positions', [CopyTradingController::class, 'positions']);
        Route::get('copy/pnl', [CopyTradingController::class, 'pnl']);
        Route::get('copy/realtime/replay', [CopyTradingController::class, 'replay']);
    });
    Route::middleware('developer.api:copy.manage')->group(function (): void {
        Route::post('copy/terms/accept', [CopyTradingController::class, 'acceptTerms'])->middleware('rate.limit');
        Route::post('copy/follow', [CopyTradingController::class, 'follow'])->middleware('rate.limit');
        Route::patch('copy/follow/{id}', [CopyTradingController::class, 'updateFollow'])->middleware('rate.limit');
        Route::delete('copy/follow/{id}', [CopyTradingController::class, 'stopFollow'])->middleware('rate.limit');
    });
    Route::middleware('developer.api:exaai.read')->group(function (): void {
        Route::get('exaai/overview', [ExaAiController::class, 'overview']);
        Route::get('exaai/strategies', [ExaAiController::class, 'strategies']);
        Route::get('exaai/allocations', [ExaAiController::class, 'allocations']);
        Route::get('exaai/allocations/active', [ExaAiController::class, 'activeAllocation']);
        Route::get('exaai/sessions/current', [ExaAiController::class, 'sessionCurrent']);
        Route::get('exaai/sessions', [DeveloperApiController::class, 'exaAiSessions']);
        Route::get('exaai/portfolio', [ExaAiController::class, 'portfolio']);
        Route::get('exaai/positions', [ExaAiController::class, 'positions']);
        Route::get('exaai/trades', [ExaAiController::class, 'trades']);
        Route::get('exaai/performance', [ExaAiController::class, 'performance']);
        Route::get('exaai/realtime/replay', [ExaAiController::class, 'realtimeReplay']);
        Route::get('exaai/readiness', [ExaAiController::class, 'readiness']);
    });
    Route::middleware('developer.api:exaai.manage')->group(function (): void {
        Route::post('exaai/terms/accept', [ExaAiController::class, 'acceptTerms'])->middleware('rate.limit');
        Route::post('exaai/allocations', [ExaAiController::class, 'allocationStore'])->middleware('rate.limit');
        Route::post('exaai/sessions', [ExaAiController::class, 'sessionStore'])->middleware('rate.limit');
        Route::post('exaai/sessions/{id}/pause', [ExaAiController::class, 'pause'])->middleware('rate.limit');
        Route::post('exaai/sessions/{id}/resume', [ExaAiController::class, 'resume'])->middleware('rate.limit');
        Route::post('exaai/sessions/{id}/stop', [ExaAiController::class, 'stop'])->middleware('rate.limit');
    });
    Route::middleware('developer.api:spot.trade')->group(function (): void {
        Route::post('spot/orders', [DeveloperApiController::class, 'createSpotOrder']);
    });
    Route::middleware('developer.api:spot.read')->group(function (): void {
        Route::get('spot/orders/{orderId}', [DeveloperApiController::class, 'getSpotOrder']);
    });
});

Route::prefix('developer')->middleware(['auth:sanctum', 'rate.limit'])->group(function (): void {
    Route::get('projects', [DeveloperPortalController::class, 'projects']);
    Route::post('projects', [DeveloperPortalController::class, 'createProject']);
    Route::post('projects/{projectId}/keys', [DeveloperPortalController::class, 'createKey']);
    Route::post('keys/{keyId}/rotate', [DeveloperPortalController::class, 'rotateKey']);
    Route::post('keys/{keyId}/disable', [DeveloperPortalController::class, 'disableKey']);
    Route::post('projects/{projectId}/sandbox/faucet', [DeveloperPortalController::class, 'faucet']);
    Route::get('projects/{projectId}/webhooks', [DeveloperPortalController::class, 'webhooks']);
    Route::post('projects/{projectId}/webhooks', [DeveloperPortalController::class, 'createWebhook']);
    Route::get('projects/{projectId}/webhook-deliveries', [DeveloperPortalController::class, 'deliveries']);
    Route::post('webhooks/{endpointId}/rotate-secret', [DeveloperPortalController::class, 'rotateWebhookSecret']);
    Route::post('webhook-deliveries/{deliveryId}/replay', [DeveloperPortalController::class, 'replayDelivery']);
});

Route::get('exapay/checkout/{token}', [ExaPayMerchantController::class, 'checkout'])->middleware('throttle:120,1');
Route::post('exapay/payment-links/{linkId}/pay', [ExaPayMerchantController::class, 'payLink'])->middleware('throttle:60,1');

Route::prefix('exapay')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
    Route::get('merchants', [ExaPayMerchantController::class, 'merchants']);
    Route::post('merchants', [ExaPayMerchantController::class, 'apply'])->middleware('rate.limit');
    Route::get('merchants/{merchantId}/overview', [ExaPayMerchantController::class, 'overview']);
    Route::get('merchants/{merchantId}/payments', [ExaPayMerchantController::class, 'payments']);
    Route::post('merchants/{merchantId}/payment-intents', [ExaPayMerchantController::class, 'createIntent'])->middleware('rate.limit');
    Route::post('payment-intents/{payIntent}/capture', [ExaPayMerchantController::class, 'capture'])->middleware('rate.limit');
    Route::get('merchants/{merchantId}/payment-links', [ExaPayMerchantController::class, 'links']);
    Route::post('merchants/{merchantId}/payment-links', [ExaPayMerchantController::class, 'createLink'])->middleware('rate.limit');
    Route::post('merchants/{merchantId}/api-keys', [ExaPayMerchantController::class, 'createApiKey'])->middleware('rate.limit');
    Route::post('merchants/{merchantId}/api-keys/{keyId}/revoke', [ExaPayMerchantController::class, 'revokeApiKey'])->middleware('rate.limit');
    Route::post('merchants/{merchantId}/refunds', [ExaPayMerchantController::class, 'refund'])->middleware('rate.limit');
    Route::post('merchants/{merchantId}/disputes', [ExaPayMerchantController::class, 'dispute'])->middleware('rate.limit');
    Route::post('merchants/{merchantId}/settlements', [ExaPayMerchantController::class, 'settlement'])->middleware('rate.limit');
    Route::get('merchants/{merchantId}/reconciliation', [ExaPayMerchantController::class, 'reconciliation']);
});

Route::prefix('v1/staking')->middleware('throttle:120,1')->group(function (): void {
    Route::get('assets', [StakingController::class, 'assets']);
    Route::get('products', [StakingController::class, 'products']);
    Route::get('products/{slug}', [StakingController::class, 'product']);
    Route::get('terms', [StakingController::class, 'terms']);
    Route::get('apy-history', [StakingController::class, 'apyHistory']);
    Route::get('exatoken-campaigns', [StakingController::class, 'exaTokenCampaigns']);
    Route::get('network-statuses', [StakingController::class, 'networkStatuses']);
    Route::get('unbonding-estimates', [StakingController::class, 'unbondingEstimates']);
    Route::post('positions', [StakingController::class, 'createPosition'])->middleware('rate.limit');

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('portfolio', [StakingController::class, 'portfolio']);
        Route::get('positions', [StakingController::class, 'positions']);
        Route::get('positions/{publicId}', [StakingController::class, 'showPosition']);
        Route::post('positions/{publicId}/unstake', [StakingController::class, 'unstake'])->middleware('rate.limit');
        Route::post('positions/{publicId}/claim-native-rewards', [StakingController::class, 'claimNativeRewards'])->middleware('rate.limit');
        Route::post('positions/{publicId}/claim-exatoken-rewards', [StakingController::class, 'claimExaTokenRewards'])->middleware('rate.limit');
        Route::patch('positions/{publicId}/auto-compound', [StakingController::class, 'updateAutoCompound'])->middleware('rate.limit');
        Route::post('terms/accept', [StakingController::class, 'acceptTerms'])->middleware('rate.limit');
        Route::get('rewards', [StakingController::class, 'rewards']);
        Route::get('transactions', [StakingController::class, 'transactions']);
    });
});

Route::get('listing/meta', [ListingPortalController::class, 'meta'])->middleware('throttle:120,1');

Route::prefix('listing')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
    Route::get('organizations', [ListingPortalController::class, 'organizations']);
    Route::post('organizations', [ListingPortalController::class, 'createOrganization'])->middleware('rate.limit');
    Route::get('applications', [ListingPortalController::class, 'applications']);
    Route::post('organizations/{organizationId}/applications', [ListingPortalController::class, 'saveDraft'])->middleware('rate.limit');
    Route::get('applications/{reference}', [ListingPortalController::class, 'show']);
    Route::post('applications/{reference}/submit', [ListingPortalController::class, 'submit'])->middleware('rate.limit');
    Route::post('applications/{reference}/messages', [ListingPortalController::class, 'message'])->middleware('rate.limit');
});

Route::post('webhooks/cards/{provider}', [ExaCardWebhookController::class, 'handle'])->middleware('throttle:120,1');

Route::prefix('cards')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
    Route::get('products', [ExaCardController::class, 'products']);
    Route::get('realtime/replay', [ExaCardController::class, 'realtimeReplay']);
    Route::get('/', [ExaCardController::class, 'index']);
    Route::post('/', [ExaCardController::class, 'store']);
    Route::post('funding-requests', [ExaCardController::class, 'fund']);
    Route::get('{cardUuid}', [ExaCardController::class, 'show']);
    Route::get('{cardUuid}/transactions', [ExaCardController::class, 'transactions']);
    Route::get('{cardUuid}/authorizations', [ExaCardController::class, 'authorizations']);
    Route::post('{cardUuid}/funding-quotes', [ExaCardController::class, 'quoteFunding']);
    Route::post('{cardUuid}/unload', [ExaCardController::class, 'unload']);
    Route::post('{cardUuid}/freeze', [ExaCardController::class, 'freeze']);
    Route::post('{cardUuid}/unfreeze', [ExaCardController::class, 'unfreeze']);
    Route::post('{cardUuid}/report-lost-stolen', [ExaCardController::class, 'reportLostOrStolen']);
    Route::post('{cardUuid}/terminate', [ExaCardController::class, 'terminate']);
    Route::put('{cardUuid}/controls', [ExaCardController::class, 'controls']);
    Route::put('{cardUuid}/limits', [ExaCardController::class, 'limits']);
    Route::post('{cardUuid}/details-token', [ExaCardController::class, 'detailsToken']);
});

Route::prefix('v1/pricing')->middleware('throttle:120,1')->group(function (): void {
    Route::get('fees', [PricingController::class, 'publicFees']);
    Route::post('preview', [PricingController::class, 'preview'])->middleware('rate.limit');
});

Route::prefix('institutional')->middleware(['auth:sanctum', 'throttle:120,1'])->group(function (): void {
    Route::post('apply', [InstitutionalController::class, 'apply'])->middleware('rate.limit');
    Route::get('applications', [InstitutionalController::class, 'applications']);
    Route::get('overview', [InstitutionalController::class, 'overview']);
    Route::get('subaccounts', [InstitutionalController::class, 'subaccounts']);
    Route::post('subaccounts', [InstitutionalController::class, 'createSubaccount'])->middleware('rate.limit');
    Route::get('transfers', [InstitutionalController::class, 'transfers']);
    Route::post('transfers', [InstitutionalController::class, 'transfer'])->middleware('rate.limit');
    Route::post('transfers/{transferUuid}/approve', [InstitutionalController::class, 'approveTransfer'])->middleware('rate.limit');
    Route::post('permissions/grant', [InstitutionalController::class, 'grantPermission'])->middleware('rate.limit');
    Route::get('realtime/replay', [InstitutionalController::class, 'realtimeReplay']);
    Route::prefix('market-making')->group(function (): void {
        Route::get('overview', [MarketMakerController::class, 'overview']);
        Route::post('apply', [MarketMakerController::class, 'apply'])->middleware('rate.limit');
        Route::get('profiles/{profileUuid}/capital/{symbol}', [MarketMakerController::class, 'capital']);
        Route::post('profiles/{profileUuid}/inventory/{symbol}', [MarketMakerController::class, 'inventory'])->middleware('rate.limit');
        Route::get('bots', [MarketMakerBotController::class, 'index']);
        Route::post('bots', [MarketMakerBotController::class, 'store'])->middleware('rate.limit');
        Route::get('bots/strategies', [MarketMakerBotController::class, 'strategies']);
        Route::post('bots/strategies', [MarketMakerBotController::class, 'createStrategy'])->middleware('rate.limit');
        Route::get('bots/{botUuid}', [MarketMakerBotController::class, 'show']);
        Route::post('bots/{botUuid}/shadow', [MarketMakerBotController::class, 'shadow'])->middleware('rate.limit');
        Route::post('bots/{botUuid}/start', [MarketMakerBotController::class, 'start'])->middleware('rate.limit');
        Route::post('bots/{botUuid}/pause', [MarketMakerBotController::class, 'pause'])->middleware('rate.limit');
        Route::post('bots/{botUuid}/reduce-only', [MarketMakerBotController::class, 'reduceOnly'])->middleware('rate.limit');
        Route::post('bots/{botUuid}/mass-cancel', [MarketMakerBotController::class, 'massCancel'])->middleware('rate.limit');
        Route::post('bots/{botUuid}/hedge', [MarketMakerBotController::class, 'hedge'])->middleware('rate.limit');
        Route::post('bots/{botUuid}/rebalance', [MarketMakerBotController::class, 'rebalance'])->middleware('rate.limit');
        Route::post('bots/{botUuid}/shock-check', [MarketMakerBotController::class, 'shock'])->middleware('rate.limit');
        Route::get('bots/{botUuid}/cycles', [MarketMakerBotController::class, 'cycles']);
    });
    Route::prefix('otc')->group(function (): void {
        Route::get('rfqs', [OtcController::class, 'rfqs']);
        Route::post('rfqs', [OtcController::class, 'requestQuote'])->middleware('rate.limit');
        Route::post('rfqs/{rfqUuid}/accept', [OtcController::class, 'accept'])->middleware('rate.limit');
        Route::get('trades', [OtcController::class, 'trades']);
    });
});

Route::prefix('admin/v1/staking')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('assets', [StakingAdminController::class, 'assets']);
    Route::patch('assets/{assetId}', [StakingAdminController::class, 'updateAsset'])->middleware('rate.limit');
    Route::post('assets/{assetId}/emergency-pause', [StakingAdminController::class, 'emergencyPause'])->middleware('rate.limit');
    Route::post('assets/{assetId}/request-mainnet-activation', [StakingAdminController::class, 'requestMainnetActivation'])->middleware('rate.limit');
    Route::get('products', [StakingAdminController::class, 'products']);
    Route::post('products', [StakingAdminController::class, 'upsertProduct'])->middleware('rate.limit');
    Route::put('products', [StakingAdminController::class, 'upsertProduct'])->middleware('rate.limit');
    Route::get('validators', [StakingAdminController::class, 'validators']);
    Route::post('validators', [StakingAdminController::class, 'upsertValidator'])->middleware('rate.limit');
    Route::get('providers/{symbol}/health', [StakingAdminController::class, 'providerHealth']);
    Route::get('approvals', [StakingAdminController::class, 'approvals']);
    Route::post('approvals/{publicId}/decision', [StakingAdminController::class, 'approve'])->middleware('rate.limit');
    Route::get('wallets', [StakingAdminController::class, 'wallets']);
    Route::get('delegation-batches', [StakingAdminController::class, 'delegationBatches']);
    Route::get('reward-batches', [StakingAdminController::class, 'rewardBatches']);
    Route::get('reconciliation-reports', [StakingAdminController::class, 'reconciliationReports']);
    Route::get('exatoken-campaigns', [StakingAdminController::class, 'exaTokenCampaigns']);
    Route::get('audit-logs', [StakingAdminController::class, 'auditLogs']);
});

Route::prefix('admin/v1/listing-center')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [ListingCenterController::class, 'overview']);
    Route::get('applications', [ListingCenterController::class, 'applications']);
    Route::post('applications/{reference}/reviews', [ListingCenterController::class, 'review'])->middleware('rate.limit');
    Route::post('applications/{reference}/recommend', [ListingCenterController::class, 'recommend'])->middleware('rate.limit');
    Route::post('applications/{reference}/approve', [ListingCenterController::class, 'approve'])->middleware('rate.limit');
    Route::post('applications/{reference}/asset-configuration', [ListingCenterController::class, 'createAssetConfiguration'])->middleware('rate.limit');
    Route::post('applications/{reference}/networks', [ListingCenterController::class, 'createNetworkConfiguration'])->middleware('rate.limit');
    Route::post('applications/{reference}/markets', [ListingCenterController::class, 'createMarket'])->middleware('rate.limit');
    Route::post('applications/{reference}/tests', [ListingCenterController::class, 'runTests'])->middleware('rate.limit');
    Route::post('applications/{reference}/final-approval', [ListingCenterController::class, 'requestFinalApproval'])->middleware('rate.limit');
    Route::post('applications/{reference}/schedule', [ListingCenterController::class, 'schedule'])->middleware('rate.limit');
    Route::post('applications/{reference}/launch', [ListingCenterController::class, 'launch'])->middleware('rate.limit');
    Route::post('applications/{reference}/token-migrations', [ListingCenterController::class, 'tokenMigration'])->middleware('rate.limit');
    Route::post('applications/{reference}/emergency-control', [ListingCenterController::class, 'emergencyControl'])->middleware('rate.limit');
    Route::post('launch-events/process-due', [ListingCenterController::class, 'processDueLaunchEvents'])->middleware('rate.limit');
    Route::get('live-assets', [ListingCenterController::class, 'liveAssets']);
    Route::get('test-runs', [ListingCenterController::class, 'testRuns']);
    Route::get('schedules', [ListingCenterController::class, 'schedules']);
    Route::get('contract-validations', [ListingCenterController::class, 'contractValidations']);
    Route::get('token-migrations', [ListingCenterController::class, 'tokenMigrations']);
    Route::get('audit-logs', [ListingCenterController::class, 'auditLogs']);
});

Route::prefix('admin/v1/institutional')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [InstitutionalOperationsController::class, 'overview']);
    Route::get('applications', [InstitutionalOperationsController::class, 'applications']);
    Route::post('applications/{uuid}/transition', [InstitutionalOperationsController::class, 'transition'])->middleware('rate.limit');
    Route::post('applications/{uuid}/activate', [InstitutionalOperationsController::class, 'activate'])->middleware('rate.limit');
    Route::get('institutions', [InstitutionalOperationsController::class, 'institutions']);
    Route::post('institutions/{institutionId}/vip', [InstitutionalOperationsController::class, 'vip'])->middleware('rate.limit');
    Route::post('institutions/{institutionId}/status', [InstitutionalOperationsController::class, 'restrict'])->middleware('rate.limit');
    Route::post('subaccounts/{uuid}/credit', [InstitutionalOperationsController::class, 'creditSubaccount'])->middleware('rate.limit');
    Route::post('fee-profiles', [InstitutionalOperationsController::class, 'feeProfile'])->middleware('rate.limit');
    Route::get('audit-logs', [InstitutionalOperationsController::class, 'auditLogs']);
});

Route::prefix('admin/v1/operations')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('readiness', [TradingOperationsController::class, 'readiness']);
    Route::post('reconciliation', [TradingOperationsController::class, 'reconciliation'])->middleware('rate.limit');
    Route::get('treasury-exposure', [TradingOperationsController::class, 'treasuryExposure']);
    Route::post('circuit-breakers', [TradingOperationsController::class, 'transitionBreaker'])->middleware('rate.limit');
    Route::post('markets/{symbol}/pause', [TradingOperationsController::class, 'pauseMarket'])->middleware('rate.limit');
    Route::post('markets/{symbol}/resume', [TradingOperationsController::class, 'resumeMarket'])->middleware('rate.limit');
    Route::post('kill-switch', [TradingOperationsController::class, 'killSwitch'])->middleware('rate.limit');
    Route::put('collateral/{asset}', [TradingOperationsController::class, 'updateCollateral'])->middleware('rate.limit');
    Route::post('insurance-fund/credit', [TradingOperationsController::class, 'insuranceCredit'])->middleware('rate.limit');
    Route::post('insurance-fund/use', [TradingOperationsController::class, 'insuranceUse'])->middleware('rate.limit');
    Route::post('load-probe', [TradingOperationsController::class, 'loadProbe'])->middleware('rate.limit');
    Route::get('incidents', [TradingOperationsController::class, 'incidents']);
});

Route::prefix('admin/v1/affiliate')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [AffiliateOperationsController::class, 'overview'])->middleware('check.permission:finance.view');
    Route::get('commissions', [AffiliateOperationsController::class, 'commissions'])->middleware('check.permission:finance.view');
    Route::get('payouts', [AffiliateOperationsController::class, 'payouts'])->middleware('check.permission:finance.view');
    Route::get('clawbacks', [AffiliateOperationsController::class, 'clawbacks'])->middleware('check.permission:finance.view');
    Route::match(['get', 'post'], 'tiers', [AffiliateOperationsController::class, 'tiers'])->middleware(['check.permission:finance.adjust.request', 'rate.limit']);
    Route::post('reconciliation', [AffiliateOperationsController::class, 'reconcile'])->middleware(['check.permission:finance.reconcile', 'rate.limit']);
    Route::get('incidents', [AffiliateOperationsController::class, 'incidents'])->middleware('check.permission:finance.reconcile');
});

Route::prefix('admin/v1/liquidity')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [LiquidityOperationsController::class, 'overview']);
    Route::get('readiness', [LiquidityOperationsController::class, 'readiness']);
    Route::get('sources', [LiquidityOperationsController::class, 'sources']);
    Route::get('sources/{source}/health', [LiquidityOperationsController::class, 'sourceHealth']);
    Route::get('books/{symbol}', [LiquidityOperationsController::class, 'consolidatedBook']);
    Route::post('sor/route-plan', [LiquidityOperationsController::class, 'planRoute'])->middleware('rate.limit');
    Route::get('treasury/inventory', [LiquidityOperationsController::class, 'treasuryInventory']);
    Route::post('treasury/buckets', [LiquidityOperationsController::class, 'allocateBucket'])->middleware('rate.limit');
    Route::get('withdrawal-reserves/{asset}', [LiquidityOperationsController::class, 'withdrawalReserve']);
    Route::get('net-exposure/{asset}', [LiquidityOperationsController::class, 'netExposure']);
    Route::post('rebalancing/{asset}', [LiquidityOperationsController::class, 'rebalance'])->middleware('rate.limit');
    Route::post('market-making/quotes', [LiquidityOperationsController::class, 'marketMakerQuote'])->middleware('rate.limit');
    Route::post('market-making/cancel-unsafe', [LiquidityOperationsController::class, 'cancelUnsafeMarketMakerQuotes'])->middleware('rate.limit');
    Route::get('venues/balances', [LiquidityOperationsController::class, 'venueBalances']);
    Route::post('reconciliation', [LiquidityOperationsController::class, 'reconciliation'])->middleware('rate.limit');
    Route::post('load-probe', [LiquidityOperationsController::class, 'loadProbe'])->middleware('rate.limit');
});

Route::prefix('admin/v1/market-makers')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [MarketMakerOperationsController::class, 'overview']);
    Route::get('applications', [MarketMakerOperationsController::class, 'applications']);
    Route::post('applications/{uuid}/transition', [MarketMakerOperationsController::class, 'transition'])->middleware('rate.limit');
    Route::post('applications/{uuid}/activate', [MarketMakerOperationsController::class, 'activate'])->middleware('rate.limit');
    Route::post('profiles/{profileUuid}/assignments', [MarketMakerOperationsController::class, 'assign'])->middleware('rate.limit');
    Route::post('profiles/{profileUuid}/agreements', [MarketMakerOperationsController::class, 'agreement'])->middleware('rate.limit');
    Route::get('profiles/{profileUuid}/capital/{symbol}', [MarketMakerOperationsController::class, 'capital']);
    Route::post('profiles/{profileUuid}/inventory/{symbol}', [MarketMakerOperationsController::class, 'inventory'])->middleware('rate.limit');
    Route::post('profiles/{profileUuid}/safety-mode', [MarketMakerOperationsController::class, 'setSafetyMode'])->middleware('rate.limit');
    Route::post('profiles/{profileUuid}/rebates', [MarketMakerOperationsController::class, 'accrueRebate'])->middleware('rate.limit');
    Route::post('rebates/{rebateUuid}/pay', [MarketMakerOperationsController::class, 'payRebate'])->middleware('rate.limit');
    Route::post('profiles/{profileUuid}/mass-cancel', [MarketMakerOperationsController::class, 'massCancel'])->middleware('rate.limit');
    Route::post('profiles/{profileUuid}/surveillance/related-overlap/{symbol}', [MarketMakerOperationsController::class, 'surveillanceOverlap'])->middleware('rate.limit');
    Route::post('markets/{symbol}/health', [MarketMakerOperationsController::class, 'health'])->middleware('rate.limit');
    Route::get('markets/{symbol}/listing-readiness', [MarketMakerOperationsController::class, 'listingReadiness']);
    Route::get('bots/overview', [MarketMakerBotOperationsController::class, 'overview']);
    Route::get('bots', [MarketMakerBotOperationsController::class, 'bots']);
    Route::post('bots/{botUuid}/approve', [MarketMakerBotOperationsController::class, 'approve'])->middleware('rate.limit');
    Route::post('bots/{botUuid}/transition', [MarketMakerBotOperationsController::class, 'transition'])->middleware('rate.limit');
    Route::post('bots/{botUuid}/live-cycle', [MarketMakerBotOperationsController::class, 'liveCycle'])->middleware('rate.limit');
    Route::post('bots/{botUuid}/mass-cancel', [MarketMakerBotOperationsController::class, 'massCancel'])->middleware('rate.limit');
    Route::post('bots/{botUuid}/shock-check', [MarketMakerBotOperationsController::class, 'shock'])->middleware('rate.limit');
    Route::post('bots/load-probe', [MarketMakerBotOperationsController::class, 'loadProbe'])->middleware('rate.limit');
});

Route::prefix('admin/v1/otc')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [OtcOperationsController::class, 'overview']);
    Route::post('markets', [OtcOperationsController::class, 'upsertMarket'])->middleware('rate.limit');
    Route::post('providers', [OtcOperationsController::class, 'registerProvider'])->middleware('rate.limit');
    Route::get('rfqs', [OtcOperationsController::class, 'rfqs']);
    Route::get('quotes', [OtcOperationsController::class, 'quotes']);
    Route::get('trades', [OtcOperationsController::class, 'trades']);
    Route::post('rfqs/{rfqUuid}/providers/{providerUuid}/quotes', [OtcOperationsController::class, 'submitQuote'])->middleware('rate.limit');
    Route::post('reconciliation', [OtcOperationsController::class, 'reconcile'])->middleware('rate.limit');
    Route::get('audit-logs', [OtcOperationsController::class, 'auditLogs']);
});

Route::prefix('admin/v1/phase15')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [Phase15OperationsController::class, 'overview']);
    Route::get('listing/{reference}/readiness', [Phase15OperationsController::class, 'listingReadiness']);
    Route::get('risk', [Phase15OperationsController::class, 'risk']);
    Route::post('reconciliation', [Phase15OperationsController::class, 'reconcile'])->middleware('rate.limit');
    Route::post('emergency', [Phase15OperationsController::class, 'emergency'])->middleware('rate.limit');
});

Route::prefix('admin/v1/compliance')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [ComplianceController::class, 'overview']);
    Route::get('products', [ComplianceController::class, 'products']);
    Route::match(['get', 'post'], 'jurisdictions', [ComplianceController::class, 'jurisdictions'])->middleware('rate.limit');
    Route::get('rules', [ComplianceController::class, 'rules']);
    Route::post('rules/submit', [ComplianceController::class, 'submitRule'])->middleware('rate.limit');
    Route::post('policy-changes/{changeId}/approve', [ComplianceController::class, 'approveChange'])->middleware('rate.limit');
    Route::post('policy-changes/{changeId}/reject', [ComplianceController::class, 'rejectChange'])->middleware('rate.limit');
    Route::post('simulate', [ComplianceController::class, 'simulate'])->middleware('rate.limit');
    Route::post('impact', [ComplianceController::class, 'impact'])->middleware('rate.limit');
    Route::get('users/{userId}/eligibility', [ComplianceController::class, 'userEligibility']);
    Route::post('emergency', [ComplianceController::class, 'emergency'])->middleware('rate.limit');
});

Route::prefix('admin/v1/finance')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [FinanceController::class, 'overview'])->middleware('check.permission:finance.view');
    Route::get('backing', [FinanceController::class, 'backing'])->middleware('check.permission:finance.view');
    Route::get('trial-balance', [FinanceController::class, 'trialBalance'])->middleware('check.permission:finance.view');
    Route::get('balance-sheet', [FinanceController::class, 'balanceSheet'])->middleware('check.permission:finance.view');
    Route::get('profit-and-loss', [FinanceController::class, 'profitAndLoss'])->middleware('check.permission:finance.view');
    Route::get('cash-flow', [FinanceController::class, 'cashFlow'])->middleware('check.permission:finance.view');
    Route::get('general-ledger', [FinanceController::class, 'generalLedger'])->middleware('check.permission:finance.view');
    Route::get('product-reconciliation', [FinanceController::class, 'productReconciliation'])->middleware('check.permission:finance.reconcile');
    Route::get('data-quality', [FinanceController::class, 'dataQuality'])->middleware('check.permission:finance.reconcile');
    Route::get('treasury', [FinanceController::class, 'treasury'])->middleware('check.permission:finance.view');
    Route::post('reports/snapshot', [FinanceController::class, 'snapshotReport'])->middleware(['check.permission:finance.export', 'rate.limit']);
    Route::post('ledger/{reference}/event', [FinanceController::class, 'postLedgerEvent'])->middleware(['check.permission:finance.reconcile', 'rate.limit']);
    Route::post('adjustments', [FinanceController::class, 'requestAdjustment'])->middleware(['check.permission:finance.adjust.request', 'rate.limit']);
    Route::post('adjustments/{uuid}/approve', [FinanceController::class, 'approveAdjustment'])->middleware(['check.permission:finance.adjust.approve', 'rate.limit']);
    Route::post('close/prepare', [FinanceController::class, 'prepareClose'])->middleware(['check.permission:finance.close.prepare', 'rate.limit']);
    Route::post('close/{periodId}/approve', [FinanceController::class, 'approveClose'])->middleware(['check.permission:finance.close.approve', 'rate.limit']);
    Route::post('close/{periodId}/reopen-request', [FinanceController::class, 'requestReopenClose'])->middleware(['check.permission:finance.close.prepare', 'rate.limit']);
    Route::post('close/{periodId}/reopen-approve', [FinanceController::class, 'approveReopenClose'])->middleware(['check.permission:finance.close.approve', 'rate.limit']);
    Route::get('obligations', [FinanceController::class, 'obligations'])->middleware('check.permission:finance.view');
    Route::post('obligations', [FinanceController::class, 'obligations'])->middleware(['check.permission:finance.reconcile', 'rate.limit']);
    Route::post('obligations/{uuid}/settle', [FinanceController::class, 'settleObligation'])->middleware(['check.permission:finance.reconcile', 'rate.limit']);
    Route::post('opening-balances', [FinanceController::class, 'openingBalances'])->middleware(['check.permission:finance.adjust.request', 'rate.limit']);
    Route::post('opening-balances/{uuid}/approve', [FinanceController::class, 'approveOpeningBalance'])->middleware(['check.permission:finance.adjust.approve', 'rate.limit']);
    Route::get('readiness', [FinanceController::class, 'readiness'])->middleware('check.permission:finance.view');
    Route::get('breaks', [FinanceController::class, 'breaks'])->middleware('check.permission:finance.reconcile');
    Route::get('dlq', [FinanceController::class, 'dlq'])->middleware('check.permission:finance.reconcile');
    Route::post('dlq/{uuid}/retry', [FinanceController::class, 'retryDlq'])->middleware(['check.permission:finance.reconcile', 'rate.limit']);
});

Route::prefix('admin/v1/pricing-rewards')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [PricingRewardsController::class, 'overview'])->middleware('check.permission:finance.view');
    Route::get('rules', [PricingRewardsController::class, 'rules'])->middleware('check.permission:finance.view');
    Route::post('rules/request', [PricingRewardsController::class, 'requestRule'])->middleware(['check.permission:finance.adjust.request', 'rate.limit']);
    Route::post('rules/changes/{changeUuid}/approve', [PricingRewardsController::class, 'approveRule'])->middleware(['check.permission:finance.adjust.approve', 'rate.limit']);
    Route::post('simulate', [PricingRewardsController::class, 'simulate'])->middleware(['check.permission:finance.view', 'rate.limit']);
    Route::get('decisions', [PricingRewardsController::class, 'decisions'])->middleware('check.permission:finance.view');
    Route::match(['get', 'post'], 'reward-rules', [PricingRewardsController::class, 'rewardRules'])->middleware(['check.permission:finance.adjust.request', 'rate.limit']);
    Route::get('reward-decisions', [PricingRewardsController::class, 'rewardDecisions'])->middleware('check.permission:finance.view');
    Route::get('shadow-comparisons', [PricingRewardsController::class, 'shadowComparisons'])->middleware('check.permission:finance.reconcile');
});

Route::prefix('admin/v1/exacard')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [ExaCardOperationsController::class, 'overview'])->middleware('check.permission:finance.view');
    Route::get('customers', [ExaCardOperationsController::class, 'customers'])->middleware('check.permission:users.view');
    Route::get('cards', [ExaCardOperationsController::class, 'cards'])->middleware('check.permission:users.view');
    Route::get('transactions', [ExaCardOperationsController::class, 'transactions'])->middleware('check.permission:finance.view');
    Route::get('funding', [ExaCardOperationsController::class, 'funding'])->middleware('check.permission:finance.view');
    Route::get('disputes', [ExaCardOperationsController::class, 'disputes'])->middleware('check.permission:finance.view');
    Route::get('treasury', [ExaCardOperationsController::class, 'treasury'])->middleware('check.permission:treasury.manage');
    Route::get('providers', [ExaCardOperationsController::class, 'providers'])->middleware('check.permission:treasury.manage');
    Route::get('revenue', [ExaCardOperationsController::class, 'revenue'])->middleware('check.permission:finance.view');
    Route::post('provider-balances', [ExaCardOperationsController::class, 'providerBalance'])->middleware(['check.permission:treasury.manage', 'rate.limit']);
    Route::post('reconciliation-runs', [ExaCardOperationsController::class, 'reconciliation'])->middleware(['check.permission:finance.reconcile', 'rate.limit']);
    Route::get('audit-logs', [ExaCardOperationsController::class, 'auditLogs'])->middleware('check.permission:logs.view');
});

Route::prefix('admin/v1/security-operations')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1', 'check.permission:security.operations'])->group(function (): void {
    Route::get('overview', [SecurityOperationsController::class, 'overview']);
    Route::post('evaluate', [SecurityOperationsController::class, 'evaluate'])->middleware('rate.limit');
    Route::match(['get', 'post'], 'cases', [SecurityOperationsController::class, 'cases'])->middleware('rate.limit');
    Route::match(['get', 'post'], 'incidents', [SecurityOperationsController::class, 'incidents'])->middleware('rate.limit');
    Route::post('emergency', [SecurityOperationsController::class, 'emergency'])->middleware('rate.limit');
    Route::post('rules', [SecurityOperationsController::class, 'rules'])->middleware('rate.limit');
});

Route::prefix('admin/v1/reliability')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1', 'check.permission:operations.view'])->group(function (): void {
    Route::get('overview', [ReliabilityOperationsController::class, 'overview']);
    Route::match(['get', 'post'], 'services', [ReliabilityOperationsController::class, 'services'])->middleware('rate.limit');
    Route::match(['get', 'post'], 'dependencies', [ReliabilityOperationsController::class, 'dependencies'])->middleware('rate.limit');
    Route::match(['get', 'post'], 'queues', [ReliabilityOperationsController::class, 'queues'])->middleware('rate.limit');
    Route::match(['get', 'post'], 'workers', [ReliabilityOperationsController::class, 'workers'])->middleware('rate.limit');
    Route::match(['get', 'post'], 'backups', [ReliabilityOperationsController::class, 'backups'])->middleware('rate.limit');
    Route::post('backups/{uuid}/restore-tested', [ReliabilityOperationsController::class, 'markRestoreTested'])->middleware('rate.limit');
    Route::match(['get', 'post'], 'alerts', [ReliabilityOperationsController::class, 'alerts'])->middleware('rate.limit');
    Route::post('alerts/{uuid}/acknowledge', [ReliabilityOperationsController::class, 'acknowledgeAlert'])->middleware('rate.limit');
    Route::post('alerts/{uuid}/resolve', [ReliabilityOperationsController::class, 'resolveAlert'])->middleware('rate.limit');
    Route::get('slos', [ReliabilityOperationsController::class, 'slos']);
    Route::match(['get', 'post'], 'recovery', [ReliabilityOperationsController::class, 'recovery'])->middleware('rate.limit');
    Route::post('recovery/{uuid}/approve', [ReliabilityOperationsController::class, 'approveRecovery'])->middleware('rate.limit');
    Route::post('recovery/{uuid}/execute', [ReliabilityOperationsController::class, 'executeRecovery'])->middleware('rate.limit');
    Route::get('config-validation', [ReliabilityOperationsController::class, 'configValidation']);
});

Route::prefix('admin/v1/games/flight')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('summary', [FlightGameAdminController::class, 'summary']);
    Route::put('settings', [FlightGameAdminController::class, 'updateSettings']);
    Route::post('tick', [FlightGameAdminController::class, 'tick']);
    Route::post('control', [FlightGameAdminController::class, 'control']);
    Route::get('reconciliation', [FlightGameAdminController::class, 'reconciliation']);
});

Route::prefix('admin/v1/fiat')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [FiatOperationsController::class, 'overview']);
    Route::get('provider-health', [FiatOperationsController::class, 'providerHealth']);
    Route::post('treasury/allocate', [FiatOperationsController::class, 'allocateTreasury'])->middleware('rate.limit');
    Route::post('withdrawal-reserves/refresh', [FiatOperationsController::class, 'refreshReserve'])->middleware('rate.limit');
    Route::post('reconciliation', [FiatOperationsController::class, 'reconciliation'])->middleware('rate.limit');
    Route::post('provider-settlements', [FiatOperationsController::class, 'recordProviderSettlement'])->middleware('rate.limit');
    Route::post('withdrawals/{withdrawalId}/complete', [FiatOperationsController::class, 'completeWithdrawal'])->middleware('rate.limit');
    Route::post('withdrawals/{withdrawalId}/fail', [FiatOperationsController::class, 'failWithdrawal'])->middleware('rate.limit');
    Route::post('refunds', [FiatOperationsController::class, 'refund'])->middleware('rate.limit');
});

Route::prefix('admin/v1/p2p')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [P2POperationsController::class, 'overview']);
    Route::get('orders', [P2POperationsController::class, 'orders']);
    Route::get('ads', [P2POperationsController::class, 'ads']);
    Route::get('merchants', [P2POperationsController::class, 'merchants']);
    Route::get('disputes', [P2POperationsController::class, 'disputes']);
    Route::get('risk', [P2POperationsController::class, 'risk']);
    Route::get('reconciliation', [P2POperationsController::class, 'reconciliation']);
    Route::get('payment-methods', [P2POperationsController::class, 'paymentMethods']);
    Route::get('escrow', [P2POperationsController::class, 'escrow']);
});

Route::prefix('admin/v1/copy-trading')->middleware(['auth:sanctum', 'admin.security', 'admin.audit', 'throttle:120,1'])->group(function (): void {
    Route::get('overview', [CopyTradingOperationsController::class, 'overview']);
    Route::post('leaders/{traderId}/approve', [CopyTradingOperationsController::class, 'approveLead'])->middleware('rate.limit');
    Route::get('orders', [CopyTradingOperationsController::class, 'copyOrders']);
    Route::get('surveillance', [CopyTradingOperationsController::class, 'surveillance']);
    Route::get('capacity', [CopyTradingOperationsController::class, 'capacity']);
    Route::post('leaders/{traderId}/control', [CopyTradingOperationsController::class, 'control'])->middleware('rate.limit');
    Route::get('public/readiness', [CopyTradingOperationsController::class, 'publicReadiness']);
    Route::post('public/request-enable', [CopyTradingOperationsController::class, 'requestEnable'])->middleware('rate.limit');
    Route::post('public/approve-enable', [CopyTradingOperationsController::class, 'approveEnable'])->middleware('rate.limit');
    Route::post('public/pause', [CopyTradingOperationsController::class, 'publicPause'])->middleware('rate.limit');
    Route::post('public/resume', [CopyTradingOperationsController::class, 'publicResume'])->middleware('rate.limit');
    Route::post('public/settings', [CopyTradingOperationsController::class, 'settings'])->middleware('rate.limit');
    Route::match(['get', 'post'], 'public/markets', [CopyTradingOperationsController::class, 'markets']);
    Route::match(['get', 'post'], 'public/jurisdictions', [CopyTradingOperationsController::class, 'jurisdictions']);
    Route::match(['get', 'post'], 'public/terms', [CopyTradingOperationsController::class, 'terms']);
    Route::match(['get', 'patch'], 'public/complaints', [CopyTradingOperationsController::class, 'complaints']);
});
Route::middleware([
    EncryptCookies::class,
    AddQueuedCookiesToResponse::class,
    StartSession::class,
])->group(function (): void {
    Route::post('register', [AuthController::class, 'register']);
    Route::post('account/check', [AuthController::class, 'checkAccount']);
    Route::post('login', [AuthController::class, 'login']);
    Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
    Route::post('reset-password', [AuthController::class, 'resetPassword']);

    Route::middleware('auth:sanctum')->group(function (): void {
        Route::get('user', [AuthController::class, 'me']);
        Route::get('me/eligibility', [EligibilityController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('2fa/verify', [AuthController::class, 'verifyTwoFactor']);

        // Activity logs - user endpoints
        Route::get('logs/my-activity', [ActivityLogController::class, 'myLogs']);
        Route::get('logs/activity/{id}', [ActivityLogController::class, 'show']);
        Route::get('logs/summary', [ActivityLogController::class, 'summary']);

        // Security & Account Management
        Route::post('profile/email/change', [AuthController::class, 'changeEmail']);
        Route::post('profile/2fa/enable', [AuthController::class, 'enable2FA']);
        Route::post('profile/2fa/disable', [AuthController::class, 'disable2FA']);
        Route::get('profile/identity', [ProfileIdentityController::class, 'identity']);
        Route::get('profile/avatars', [ProfileIdentityController::class, 'avatars']);
        Route::post('profile/avatar', [ProfileIdentityController::class, 'selectAvatar'])->middleware('rate.limit');
        Route::post('profile/initials', [ProfileIdentityController::class, 'useInitials'])->middleware('rate.limit');
        Route::post('profile/image', [ProfileIdentityController::class, 'upload'])->middleware('rate.limit');
        Route::delete('profile/image', [ProfileIdentityController::class, 'removeImage'])->middleware('rate.limit');
        Route::patch('profile/visibility', [ProfileIdentityController::class, 'updateVisibility'])->middleware('rate.limit');
        Route::get('profile/images/{user}/{variant}', [ProfileIdentityController::class, 'image'])->name('profile.image');
    });

    Route::middleware(['auth:sanctum', 'log.activity'])->group(function (): void {
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::get('points', [RewardController::class, 'points']);
    Route::get('checkin/history', [RewardController::class, 'checkInHistory']);
    Route::post('checkin', [RewardController::class, 'checkInForHome'])->middleware('throttle:6,1');
    Route::get('preferences/language-region', [UserPreferenceController::class, 'languageRegion']);
    Route::patch('preferences/language-region', [UserPreferenceController::class, 'updateLanguageRegion']);
    Route::get('preferences/currency', [UserPreferenceController::class, 'currencyPreference']);
    Route::patch('preferences/currency', [UserPreferenceController::class, 'updateCurrencyPreference']);
    Route::get('preferences/dashboard', [UserPreferenceController::class, 'dashboard']);
    Route::put('preferences/dashboard', [UserPreferenceController::class, 'updateDashboard'])->middleware('throttle:20,1');
    Route::delete('preferences/dashboard', [UserPreferenceController::class, 'resetDashboard'])->middleware('throttle:20,1');
    Route::get('dashboard', [DashboardController::class, 'show']);
    Route::get('personalized-content/dashboard', [PersonalizedContentController::class, 'dashboard']);
    Route::get('personalized-content/feed', [PersonalizedContentController::class, 'feed']);
    Route::post('personalized-content/{content}/{interaction}', [PersonalizedContentController::class, 'interact'])->middleware('throttle:120,1');
});

Route::get('exaskills/verify/{credential}', [ExaSkillsController::class, 'verifyCredential']);

Route::get('events/subscribe', [EventStreamController::class, 'subscribe']);
Route::get('events/campaigns/subscribe', [EventStreamController::class, 'subscribeCampaigns']);

Route::get('games/flight/state', [FlightGameController::class, 'state']);
Route::get('games/flight/history', [FlightGameController::class, 'history']);
Route::get('games/flight/rounds/{roundUuid}/fairness', [FlightGameController::class, 'fairness']);

Route::post('blockchain/event', [BlockchainEventController::class, 'store'])
    ->middleware('node.webhook');

Route::prefix('webhooks')->group(function (): void {
    Route::post('deposits', [WebhookController::class, 'deposit']);
    Route::post('payment/{provider}', [PaymentController::class, 'webhook']);
    Route::get('deposit-addresses', [WebhookController::class, 'depositAddresses']);
    Route::post('withdrawals/confirm', [WebhookController::class, 'withdrawalConfirm']);
    Route::post('treasury-deposits', [WebhookController::class, 'treasuryDeposit']);
    Route::post('nft/events', [WebhookController::class, 'nftEvent']);
    
    // Fiat withdrawal webhooks
    Route::post('fiat/flutterwave', [WebhookController::class, 'flutterwaveWithdrawal']);
    Route::post('fiat/nomba', [WebhookController::class, 'nombaWithdrawal']);
    Route::post('fiat-withdrawals/{provider}', [FiatWithdrawalController::class, 'webhook']);
    Route::post('fiat-payments/{provider}', [FiatController::class, 'webhook'])->middleware('throttle:120,1');
});

Route::prefix('admin')->group(function (): void {
    Route::post('login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin.security', 'admin.audit'])->group(function (): void {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::get('me', [AdminAuthController::class, 'me']);
        Route::get('dashboard-personalization/insights', [DashboardController::class, 'insights']);
        Route::prefix('personalized-content')->middleware('check.permission:campaign.manage')->group(function (): void {
            Route::get('/', [PersonalizedContentAdminController::class, 'index']);
            Route::post('/', [PersonalizedContentAdminController::class, 'store'])->middleware('rate.limit');
            Route::patch('{content}', [PersonalizedContentAdminController::class, 'update'])->middleware('rate.limit');
            Route::post('{content}/{action}', [PersonalizedContentAdminController::class, 'transition'])->whereIn('action', ['publish', 'pause', 'unpublish', 'archive', 'expire'])->middleware('rate.limit');
            Route::post('{content}/duplicate', [PersonalizedContentAdminController::class, 'duplicate'])->middleware('rate.limit');
            Route::post('events/ingest', [PersonalizedContentAdminController::class, 'ingestEvent'])->middleware('rate.limit');
        });

        // User Management
        Route::get('users', [AdminPlatformController::class, 'users']);
        Route::get('users/profile-images/review', [AdminPlatformController::class, 'profileImageReviewQueue']);
        Route::get('users/{id}/profile-identity', [AdminPlatformController::class, 'userProfileIdentity']);
        Route::post('users/{id}/profile-image/remove', [AdminPlatformController::class, 'removeUserProfileImage']);
        Route::post('users/{id}/profile-image/suspend', [AdminPlatformController::class, 'suspendUserProfileImages']);
        Route::get('users/{id}', [AdminPlatformController::class, 'user']);
        Route::post('users/{id}/freeze', [AdminPlatformController::class, 'freezeUser']);
        Route::post('users/{id}/unfreeze', [AdminPlatformController::class, 'unfreezeUser']);
        Route::post('users/{id}/adjust-balance', [AdminPlatformController::class, 'adjustUserBalance']);
        Route::get('users/{id}/logs', [AdminPlatformController::class, 'userLogs']);
        Route::get('users/{id}/wallets', [AdminPlatformController::class, 'userWallets']);
        Route::get('users/{id}/trades', [AdminPlatformController::class, 'userTrades']);
        Route::get('users/{id}/rewards', [AdminPlatformController::class, 'userRewards']);

        // Wallet Management
        Route::get('wallets', [AdminPlatformController::class, 'wallets']);
        Route::post('wallets/{id}/freeze', [AdminPlatformController::class, 'freezeWallet']);
        Route::post('wallets/{id}/adjust', [AdminPlatformController::class, 'adjustWallet']);

        // Transaction Management
        Route::get('transactions', [AdminPlatformController::class, 'transactions']);

        // Trading Pairs Management
        Route::get('trading', [AdminPlatformController::class, 'pairs']);
        Route::post('trading', [AdminPlatformController::class, 'createPair']);
        Route::get('margin', [MarginController::class, 'adminOverview']);
        Route::get('margin/readiness', [MarginController::class, 'readiness']);
        Route::post('margin/load-probe', [MarginController::class, 'runLoadProbe'])->middleware('rate.limit');
        Route::post('margin/liquidations/{liquidationId}/execute', [MarginController::class, 'executeLiquidation'])->middleware('rate.limit');

        // KYC Management
        Route::get('kyc', [KycAdminController::class, 'flagged']);
        Route::get('kyc/{id}', [KycAdminController::class, 'show']);
        Route::post('kyc/{id}/approve', [KycAdminController::class, 'approve']);
        Route::post('kyc/{id}/reject', [KycAdminController::class, 'reject']);

        // Treasury Management
        Route::get('treasury', [TreasuryController::class, 'wallets']);
        Route::get('treasury/settings', [AdminSettingController::class, 'treasurySettings']);
        Route::post('treasury/wallets', [TreasuryController::class, 'createWallet']);
        Route::post('treasury/sweep', [TreasuryController::class, 'initiateSweep']);
        Route::get('treasury/transactions', [TreasuryController::class, 'transactions']);
        Route::post('treasury/transactions/{id}/confirm', [TreasuryController::class, 'confirmTransaction']);

        // Settings Management
        Route::get('settings', [AdminSettingController::class, 'index']);
        Route::put('settings/{key}', [AdminSettingController::class, 'update']);

        // Activity Logs Management
        Route::get('logs/activity', [ActivityLogController::class, 'allLogs']);
        Route::get('logs/user/{userId}', [ActivityLogController::class, 'userLogs']);
        Route::get('logs/admin-actions', [ActivityLogController::class, 'adminLogs']);
        Route::get('logs/suspicious', [ActivityLogController::class, 'suspiciousActivity']);
        Route::get('logs/ip-activity', [ActivityLogController::class, 'ipActivity']);
        Route::get('logs/export', [ActivityLogController::class, 'export']);

        // Generic module endpoints - will serve module data based on the module key
        Route::get('module/{module}', [AdminPlatformController::class, 'getModuleData']);
        
        // Placeholder endpoints for modules not yet fully implemented
        Route::get('p2p', fn () => response()->json(['data' => [], 'message' => 'P2P module data']));
        Route::get('staking', fn () => response()->json(['data' => [], 'message' => 'Staking module data']));
        Route::get('rewards', fn () => response()->json(['data' => [], 'message' => 'Rewards module data']));
        Route::get('nft', fn () => response()->json(['data' => [], 'message' => 'NFT module data']));
        Route::get('agritech', fn () => response()->json(['data' => [], 'message' => 'AgriTech module data']));
        Route::get('sports', fn () => response()->json(['data' => [], 'message' => 'Sports module data']));
        Route::get('edtech', fn () => response()->json(['data' => [], 'message' => 'EdTech module data']));
        Route::get('exaskills', [ExaSkillsAdminController::class, 'overview']);
        Route::post('exaskills/courses/{course}/review', [ExaSkillsAdminController::class, 'reviewCourse'])->middleware('rate.limit');
        Route::get('exaskills/media/{asset}', [ExaSkillsAdminController::class, 'media']);
        Route::post('exaskills/instructor-payouts/{payout}/approve', [ExaSkillsAdminController::class, 'approvePayout'])->middleware('rate.limit');
        Route::post('exaskills/tax-policies', [ExaSkillsAdminController::class, 'createTaxPolicy'])->middleware('rate.limit');
        Route::post('exaskills/opportunities/{opportunity}/moderate', [ExaSkillsAdminController::class, 'moderateOpportunity'])->middleware('rate.limit');
        Route::post('exaskills/credentials/{credential}/revoke', [ExaSkillsAdminController::class, 'revokeCredential'])->middleware('rate.limit');
        Route::get('exaskills/reconciliation', [ExaSkillsAdminController::class, 'reconciliation']);
        Route::post('exaskills/challenges/{challenge}/payout-winner', [ExaSkillsAdminController::class, 'payoutChallengeWinner'])->middleware('rate.limit');
        Route::get('crowdfunding', [CrowdfundingOperationsController::class, 'overview'])->middleware('check.permission:crowdfunding.view');
        Route::prefix('crowdfunding')->middleware('check.permission:crowdfunding.view')->group(function (): void {
            Route::get('overview', [CrowdfundingOperationsController::class, 'overview']);
            Route::get('campaigns', [CrowdfundingOperationsController::class, 'campaigns']);
            Route::get('creators', [CrowdfundingOperationsController::class, 'creators']);
            Route::get('records', [CrowdfundingOperationsController::class, 'records']);
            Route::get('operations', [CrowdfundingOperationsController::class, 'operations']);
            Route::put('operations', [CrowdfundingOperationsController::class, 'updateOperations'])->middleware(['check.permission:crowdfunding.manage', 'rate.limit']);
            Route::get('reconciliation', [CrowdfundingOperationsController::class, 'reconciliation'])->middleware('check.permission:crowdfunding.reconcile');
            Route::get('documents/{document}', [CrowdfundingOperationsController::class, 'document'])->middleware('check.permission:crowdfunding.review');
            Route::post('documents/{document}/review', [CrowdfundingOperationsController::class, 'reviewDocument'])->middleware(['check.permission:crowdfunding.review', 'rate.limit']);
            Route::post('comments/{comment}/moderate', [CrowdfundingOperationsController::class, 'moderateComment'])->middleware(['check.permission:crowdfunding.manage', 'rate.limit']);
            Route::post('assignments', [CrowdfundingOperationsController::class, 'assignReview'])->middleware(['check.permission:crowdfunding.review', 'rate.limit']);
            Route::post('campaigns/{campaign}/review', [CrowdfundingOperationsController::class, 'review'])->middleware(['check.permission:crowdfunding.review', 'rate.limit']);
            Route::post('campaigns/{campaign}/milestones', [CrowdfundingOperationsController::class, 'milestone'])->middleware(['check.permission:crowdfunding.milestones', 'rate.limit']);
            Route::post('milestones/{milestone}/review', [CrowdfundingOperationsController::class, 'reviewMilestone'])->middleware(['check.permission:crowdfunding.milestones', 'rate.limit']);
            Route::post('milestones/{milestone}/release', [CrowdfundingOperationsController::class, 'releaseMilestone'])->middleware(['check.permission:crowdfunding.release', 'rate.limit']);
            Route::post('campaigns/{campaign}/refund', [CrowdfundingOperationsController::class, 'refund'])->middleware(['check.permission:crowdfunding.refund', 'rate.limit']);
        });
        Route::get('lottery', fn () => response()->json(['data' => [], 'message' => 'Lottery module data']));
        Route::get('giftcard', fn () => response()->json(['data' => [], 'message' => 'GiftCard module data']));
        Route::get('campaigns', fn () => response()->json(['data' => [], 'message' => 'Campaigns module data']));
        Route::get('notifications', fn () => response()->json(['data' => [], 'message' => 'Notifications module data']));
        Route::get('logs', fn () => response()->json(['data' => [], 'message' => 'Audit logs module data']));
        Route::get('security', fn () => response()->json(['data' => [], 'message' => 'Security module data']));
        Route::get('admins', fn () => response()->json(['data' => [], 'message' => 'Admins module data']));
        Route::get('roles', fn () => response()->json(['data' => [], 'message' => 'Roles module data']));
        Route::get('permissions', fn () => response()->json(['data' => [], 'message' => 'Permissions module data']));
        Route::get('system', fn () => response()->json(['data' => [], 'message' => 'System monitor module data']));
    });
});

Route::middleware(['dev.auth', 'security.layer'])->group(function (): void {
    Route::prefix('accounts')->group(function (): void {
        Route::get('/', [AccountController::class, 'index']);
        Route::get('funding', [AccountController::class, 'funding']);
        Route::get('unified-trading', [AccountController::class, 'unifiedTrading']);
        Route::get('unified-trading/balances', [AccountController::class, 'unifiedTradingBalances']);
        Route::post('transfer', [AccountController::class, 'transfer'])->middleware('rate.limit');
        Route::get('transfers', [AccountController::class, 'transferHistory']);
        Route::get('closure/readiness', [AccountController::class, 'closureReadiness']);
    });
    Route::prefix('exaskills')->group(function (): void {
        Route::get('home', [ExaSkillsController::class, 'home']);
        Route::get('categories', [ExaSkillsController::class, 'categories']);
        Route::get('courses', [ExaSkillsController::class, 'courses']);
        Route::post('courses', [ExaSkillsController::class, 'createCourse'])->middleware('rate.limit');
        Route::get('courses/{course}', [ExaSkillsController::class, 'course']);
        Route::post('courses/{course}/lessons', [ExaSkillsController::class, 'addLesson'])->middleware('rate.limit');
        Route::post('courses/{course}/media', [ExaSkillsController::class, 'uploadMedia'])->middleware('rate.limit');
        Route::get('media/{asset}', [ExaSkillsController::class, 'media']);
        Route::post('courses/{course}/submit-review', [ExaSkillsController::class, 'submitCourse'])->middleware('rate.limit');
        Route::post('courses/{course}/publish', [ExaSkillsController::class, 'publishCourse'])->middleware('rate.limit');
        Route::post('courses/{course}/enroll', [ExaSkillsController::class, 'enroll'])->middleware('rate.limit');
        Route::post('courses/{course}/purchase', [ExaSkillsController::class, 'purchaseCourse'])->middleware('rate.limit');
        Route::post('courses/{course}/lessons/{lesson}/complete', [ExaSkillsController::class, 'completeLesson'])->middleware('rate.limit');
        Route::post('courses/{course}/assessment/attempts', [ExaSkillsController::class, 'submitAssessment'])->middleware('rate.limit');
        Route::get('dashboard', [ExaSkillsController::class, 'dashboard']);
        Route::get('subscriptions/plans', [ExaSkillsController::class, 'subscriptionPlans']);
        Route::get('subscriptions/current', [ExaSkillsController::class, 'currentSubscription']);
        Route::post('subscriptions', [ExaSkillsController::class, 'activateSubscription'])->middleware('rate.limit');
        Route::post('subscriptions/{subscription}/renew', [ExaSkillsController::class, 'renewSubscription'])->middleware('rate.limit');
        Route::post('subscriptions/{subscription}/cancel', [ExaSkillsController::class, 'cancelSubscription'])->middleware('rate.limit');
        Route::post('instructors/apply', [ExaSkillsController::class, 'instructorApply'])->middleware('rate.limit');
        Route::post('instructors/tax-profile', [ExaSkillsController::class, 'taxProfile'])->middleware('rate.limit');
        Route::post('instructors/payouts', [ExaSkillsController::class, 'requestPayout'])->middleware('rate.limit');
        Route::get('instructors/payouts/{payout}', [ExaSkillsController::class, 'payoutStatus']);
        Route::get('challenges', [ExaSkillsController::class, 'challenges']);
        Route::post('challenges/{challenge}/submissions', [ExaSkillsController::class, 'submitChallenge'])->middleware('rate.limit');
        Route::post('challenges/{challenge}/fund', [ExaSkillsController::class, 'fundChallenge'])->middleware('rate.limit');
        Route::get('opportunities', [ExaSkillsController::class, 'opportunities']);
        Route::post('opportunities/{opportunity}/applications', [ExaSkillsController::class, 'applyOpportunity'])->middleware('rate.limit');
        Route::post('business/organizations', [ExaSkillsController::class, 'createOrganization'])->middleware('rate.limit');
        Route::get('business/organizations/{organization}', [ExaSkillsController::class, 'businessDashboard']);
        Route::post('business/organizations/{organization}/members', [ExaSkillsController::class, 'inviteBusinessMember'])->middleware('rate.limit');
        Route::post('business/organizations/{organization}/seats', [ExaSkillsController::class, 'createBusinessSeats'])->middleware('rate.limit');
        Route::post('business/organizations/{organization}/programs', [ExaSkillsController::class, 'createTrainingProgram'])->middleware('rate.limit');
        Route::post('business/organizations/{organization}/opportunities', [ExaSkillsController::class, 'createEmployerOpportunity'])->middleware('rate.limit');
    });
    Route::prefix('crowdfunding')->group(function (): void {
        Route::get('campaigns', [CrowdfundingController::class, 'index']);
        Route::get('creator/dashboard', [CrowdfundingController::class, 'creatorDashboard']);
        Route::get('backer/dashboard', [CrowdfundingController::class, 'backerDashboard']);
        Route::post('campaigns', [CrowdfundingController::class, 'store'])->middleware('rate.limit');
        Route::get('campaigns/{campaign}', [CrowdfundingController::class, 'show']);
        Route::get('campaigns/{campaign}/logs', [CrowdfundingController::class, 'logs']);
        Route::get('campaigns/{campaign}/comments', [CrowdfundingController::class, 'comments']);
        Route::post('campaigns/{campaign}/comments', [CrowdfundingController::class, 'comment'])->middleware('rate.limit');
        Route::post('comments/{comment}/report', [CrowdfundingController::class, 'reportComment'])->middleware('rate.limit');
        Route::post('campaigns/{campaign}/documents', [CrowdfundingController::class, 'uploadDocument'])->middleware('rate.limit');
        Route::get('documents/{document}', [CrowdfundingController::class, 'document']);
        Route::post('campaigns/{campaign}/submit', [CrowdfundingController::class, 'submit'])->middleware('rate.limit');
        Route::post('campaigns/{campaign}/contributions', [CrowdfundingController::class, 'pledge'])->middleware('rate.limit');
        Route::post('campaigns/{campaign}/pledges', [CrowdfundingController::class, 'pledge'])->middleware('rate.limit');
        Route::post('campaigns/{campaign}/updates', [CrowdfundingController::class, 'update'])->middleware('rate.limit');
        Route::post('milestones/{milestone}/submit', [CrowdfundingController::class, 'milestoneSubmit'])->middleware('rate.limit');
        Route::get('pledges', [CrowdfundingController::class, 'history']);
    });
    Route::prefix('v1/crowdfunding')->group(function (): void {
        Route::get('campaigns', [CrowdfundingController::class, 'index']);
        Route::get('creator/dashboard', [CrowdfundingController::class, 'creatorDashboard']);
        Route::get('backer/dashboard', [CrowdfundingController::class, 'backerDashboard']);
        Route::post('campaigns', [CrowdfundingController::class, 'store'])->middleware('rate.limit');
        Route::get('campaigns/{campaign}', [CrowdfundingController::class, 'show']);
        Route::get('campaigns/{campaign}/logs', [CrowdfundingController::class, 'logs']);
        Route::get('campaigns/{campaign}/comments', [CrowdfundingController::class, 'comments']);
        Route::post('campaigns/{campaign}/comments', [CrowdfundingController::class, 'comment'])->middleware('rate.limit');
        Route::post('comments/{comment}/report', [CrowdfundingController::class, 'reportComment'])->middleware('rate.limit');
        Route::post('campaigns/{campaign}/documents', [CrowdfundingController::class, 'uploadDocument'])->middleware('rate.limit');
        Route::get('documents/{document}', [CrowdfundingController::class, 'document']);
        Route::post('campaigns/{campaign}/submit', [CrowdfundingController::class, 'submit'])->middleware('rate.limit');
        Route::post('campaigns/{campaign}/pledges', [CrowdfundingController::class, 'pledge'])->middleware('rate.limit');
        Route::post('campaigns/{campaign}/updates', [CrowdfundingController::class, 'update'])->middleware('rate.limit');
        Route::post('milestones/{milestone}/submit', [CrowdfundingController::class, 'milestoneSubmit'])->middleware('rate.limit');
        Route::get('pledges', [CrowdfundingController::class, 'history']);
    });
    Route::prefix('wallet')->group(function (): void {
        Route::get('balances', [WalletController::class, 'balances']);
        Route::get('deposit-address', [CustodyController::class, 'depositAddress']);
        Route::post('withdrawal-quote', [CustodyController::class, 'withdrawalQuote'])->middleware('rate.limit');
        Route::post('custody-withdrawals', [CustodyController::class, 'requestWithdrawal'])->middleware('rate.limit');
        Route::get('deposit-addresses', [WalletController::class, 'depositAddresses']);
        Route::post('deposit-address', [WalletController::class, 'generateDepositAddress']);
        Route::post('transfer', [WalletController::class, 'transfer']);
        Route::post('internal-transfer', [WalletController::class, 'internalTransfer']);
        Route::post('withdraw', [WalletController::class, 'withdraw'])->middleware('rate.limit');
        Route::get('withdraw/meta', [WithdrawalCenterController::class, 'meta']);
        Route::get('withdraw/history', [WithdrawalCenterController::class, 'history']);
        Route::post('withdraw/preview', [WithdrawalCenterController::class, 'preview']);
        Route::post('withdraw/internal-lookup', [WithdrawalCenterController::class, 'internalLookup']);
        Route::post('withdraw/internal-transfer', [WithdrawalCenterController::class, 'internalTransfer'])->middleware('rate.limit');
        Route::post('withdraw/on-chain', [WithdrawalCenterController::class, 'onChain'])->middleware('rate.limit');
        Route::get('withdraw/fiat/banks', [WithdrawalCenterController::class, 'fiatBanks']);
        Route::get('transactions', [WalletController::class, 'transactions']);
        Route::get('withdrawals', [WalletController::class, 'withdrawals']);
        Route::get('deposit/meta', [WalletController::class, 'depositMeta']);
        Route::get('deposit/history', [WalletController::class, 'depositHistory']);
        Route::post('deposit/address', [WalletController::class, 'depositAddress'])->middleware('rate.limit');
        Route::post('deposit/fiat-instructions', [WalletController::class, 'fiatDepositInstructions'])->middleware('rate.limit');
        Route::post('deposit/fiat-intents/{reference}/mark-paid', [WalletController::class, 'markFiatDepositIntentPaid'])->middleware('rate.limit');
        Route::post('deposit/fiat-intents/{reference}/settle', [WalletController::class, 'settleFiatDepositIntent'])->middleware('rate.limit');
        Route::get('{currency}', [WalletController::class, 'show']);
    });

    Route::prefix('transactions')->group(function (): void {
        Route::get('/', [TransactionController::class, 'index']);
        Route::get('mine', [TransactionController::class, 'userTransactions']);
        Route::get('{id}', [TransactionController::class, 'show']);
        Route::post('transfer', [TransactionController::class, 'transfer'])->middleware('rate.limit');
        Route::post('withdraw', [TransactionController::class, 'withdraw'])->middleware('rate.limit');
        Route::post('deposit-webhook', [TransactionController::class, 'depositWebhook']);
    });

    Route::prefix('trade')->group(function (): void {
        Route::get('markets', [TradeController::class, 'markets']);
        Route::get('order-book', [TradeController::class, 'orderBookByQuery']);
        Route::get('trades', [TradeController::class, 'tradesByQuery']);
        Route::get('candles', [TradeController::class, 'candlesByQuery']);
        Route::get('klines', [TradeController::class, 'klines']);
        Route::get('orders', [TradeController::class, 'openOrders']);
        Route::get('history', [TradeController::class, 'userTrades']);
        Route::post('markets', [TradeController::class, 'createMarket'])->middleware('rate.limit');
        Route::post('orders', [TradeController::class, 'placeOrder'])->middleware('rate.limit');
        Route::delete('orders/{orderUuid}', [TradeController::class, 'cancelOrder'])->middleware('rate.limit');
        Route::post('swap', [TradeController::class, 'swap'])->middleware('rate.limit');
        Route::get('orders/open', [TradeController::class, 'openOrders']);
        Route::get('order-book/{pair}', [TradeController::class, 'orderBook']);
        Route::get('trades/{pair}', [TradeController::class, 'trades']);
        Route::get('candles/{pair}', [TradeController::class, 'candles']);
    });

    Route::prefix('swap')->group(function (): void {
        Route::get('meta', [SwapController::class, 'meta']);
        Route::get('history', [SwapController::class, 'history']);
        Route::get('reconciliation', [SwapController::class, 'reconciliation']);
        Route::post('quote', [SwapController::class, 'quote']);
        Route::post('execute', [SwapController::class, 'execute'])->middleware('rate.limit');
        Route::get('{swapId}', [SwapController::class, 'show']);
    });

    Route::prefix('margin')->middleware(['2fa', 'throttle:120,1'])->group(function (): void {
        Route::get('overview', [MarginController::class, 'overview']);
        Route::get('accounts', [MarginController::class, 'accounts']);
        Route::post('accounts', [MarginController::class, 'createAccount'])->middleware('rate.limit');
        Route::get('assets', [MarginController::class, 'assets']);
        Route::get('pools', [MarginController::class, 'pools']);
        Route::post('pools/fund', [MarginController::class, 'fundPool'])->middleware('admin.security');
        Route::get('health', [MarginController::class, 'health']);
        Route::post('transfer', [MarginController::class, 'transfer'])->middleware('rate.limit');
        Route::post('borrow', [MarginController::class, 'borrow'])->middleware('rate.limit');
        Route::get('loans', [MarginController::class, 'loans']);
        Route::get('orders', [MarginController::class, 'orders']);
        Route::get('realtime/snapshot', [MarginController::class, 'realtimeSnapshot']);
        Route::post('orders', [MarginController::class, 'placeOrder'])->middleware('rate.limit');
        Route::post('orders/{marginOrderUuid}/cancel', [MarginController::class, 'cancelOrder'])->middleware('rate.limit');
        Route::post('loans/{loanUuid}/repay', [MarginController::class, 'repay'])->middleware('rate.limit');
        Route::post('loans/{loanUuid}/accrue', [MarginController::class, 'accrue']);
        Route::get('interest', [MarginController::class, 'interest']);
        Route::post('liquidation-check', [MarginController::class, 'liquidationCheck']);
        Route::get('liquidations', [MarginController::class, 'liquidations']);
        Route::get('reconciliation', [MarginController::class, 'reconcile'])->middleware('admin.security');
    });

    Route::prefix('payments')->group(function (): void {
        Route::post('initiate', [PaymentController::class, 'initiate']);
    });

    Route::prefix('portfolio')->group(function (): void {
        Route::get('/', [PortfolioController::class, 'show']);
    });

    Route::prefix('campaigns')->group(function (): void {
        Route::post('generate', [CampaignController::class, 'generate']);
    });

    Route::prefix('ledger')->group(function (): void {
        Route::post('transactions', [LedgerController::class, 'createTransaction']);
        Route::post('entries', [LedgerController::class, 'addEntry']);
        Route::post('commit', [LedgerController::class, 'commit']);
        Route::post('rollback', [LedgerController::class, 'rollback']);
        Route::post('operations', [LedgerController::class, 'operation']);
        Route::post('fees', [LedgerController::class, 'feeOperation']);
    });

    Route::prefix('withdrawals')->group(function (): void {
        Route::post('initiate', [WithdrawalController::class, 'initiate'])->middleware('rate.limit');
        Route::get('{reference}/status', [WithdrawalController::class, 'status']);
        Route::post('{reference}/cancel', [WithdrawalController::class, 'cancel'])->middleware('rate.limit');
    });

    Route::prefix('fiat-withdrawals')->group(function (): void {
        Route::get('meta', [FiatWithdrawalController::class, 'meta']);
        Route::post('quote', [FiatWithdrawalController::class, 'quote']);
        Route::post('resolve-account', [FiatWithdrawalController::class, 'resolveAccount'])->middleware('rate.limit');
        Route::get('beneficiaries', [FiatWithdrawalController::class, 'beneficiaries']);
        Route::post('beneficiaries', [FiatWithdrawalController::class, 'storeBeneficiary'])->middleware('rate.limit');
        Route::delete('beneficiaries/{beneficiaryId}', [FiatWithdrawalController::class, 'deleteBeneficiary'])->middleware('rate.limit');
        Route::post('intents', [FiatWithdrawalController::class, 'createIntent'])->middleware('rate.limit');
        Route::get('intents/{uuid}', [FiatWithdrawalController::class, 'showIntent']);
        Route::post('intents/{uuid}/verification-challenges', [FiatWithdrawalController::class, 'createVerificationChallenge'])->middleware('rate.limit');
        Route::post('intents/{uuid}/verify', [FiatWithdrawalController::class, 'verify'])->middleware('rate.limit');
        Route::get('history', [FiatWithdrawalController::class, 'history']);
        Route::post('initiate', [FiatWithdrawalController::class, 'initiate']);
        Route::get('banks', [FiatWithdrawalController::class, 'supportedBanks']);
        Route::get('withdrawal/{withdrawalId}/status', [FiatWithdrawalController::class, 'withdrawalStatus']);
    });

    Route::prefix('futures')->middleware(['2fa', 'throttle:120,1'])->group(function (): void {
        Route::get('markets', [FuturesController::class, 'markets']);
        Route::post('orders', [FuturesController::class, 'placeOrder'])->middleware('rate.limit');
        Route::post('orders/validate', [FuturesController::class, 'validateOrder']);
        Route::post('orders/conditional', [FuturesController::class, 'createConditionalOrder'])->middleware('rate.limit');
        Route::post('orders/trigger-conditionals', [FuturesController::class, 'triggerConditionals']);
        Route::post('orders/batch-cancel', [FuturesController::class, 'batchCancelOrders'])->middleware('rate.limit');
        Route::delete('orders/{orderUuid}', [FuturesController::class, 'cancelOrder']);
        Route::get('orders/{orderUuid}', [FuturesController::class, 'orderDetails']);
        Route::get('orders/open', [FuturesController::class, 'openOrders']);
        Route::get('margin/status', [FuturesController::class, 'marginStatus']);
        Route::post('margin/type', [FuturesController::class, 'setMarginType']);
        Route::get('positions', [FuturesController::class, 'positions']);
        Route::get('trades', [FuturesController::class, 'trades']);
        Route::post('copy/follow', [FuturesController::class, 'followTrader']);
        Route::delete('copy/follow/{traderId}', [FuturesController::class, 'unfollowTrader']);
        Route::post('market/tick', [FuturesController::class, 'marketTick']);
    });

    Route::prefix('v1/copy-trading')->middleware(['2fa', 'throttle:120,1'])->group(function (): void {
        Route::get('eligibility', [CopyTradingController::class, 'eligibility']);
        Route::get('leaders', [CopyTradingController::class, 'leaders']);
        Route::get('lead/profile', [CopyTradingController::class, 'leadProfile']);
        Route::get('lead/performance', [CopyTradingController::class, 'leadPerformance']);
        Route::get('lead/earnings', [CopyTradingController::class, 'leadEarnings']);
        Route::get('leaders/{id}', [CopyTradingController::class, 'leader']);
        Route::post('follow', [CopyTradingController::class, 'follow'])->middleware('rate.limit');
        Route::patch('follow/{id}', [CopyTradingController::class, 'updateFollow'])->middleware('rate.limit');
        Route::delete('follow/{id}', [CopyTradingController::class, 'stopFollow'])->middleware('rate.limit');
        Route::get('relationships', [CopyTradingController::class, 'relationships']);
        Route::get('orders', [CopyTradingController::class, 'orders']);
        Route::get('positions', [CopyTradingController::class, 'positions']);
        Route::get('pnl', [CopyTradingController::class, 'pnl']);
        Route::get('realtime/replay', [CopyTradingController::class, 'replay']);
        Route::post('terms/accept', [CopyTradingController::class, 'acceptTerms'])->middleware('rate.limit');
        Route::post('complaints', [CopyTradingController::class, 'complain'])->middleware('rate.limit');
        Route::post('lead/apply', [CopyTradingController::class, 'applyLead'])->middleware('rate.limit');
    });

    Route::prefix('staking')->group(function (): void {
        Route::any('{legacy?}', [StakingController::class, 'unavailable'])->where('legacy', '.*');
    });

    Route::prefix('fiat')->group(function (): void {
        Route::get('currencies', [FiatController::class, 'currencies']);
        Route::get('banks', [FiatController::class, 'banks']);
        Route::post('bank-accounts/verify', [FiatController::class, 'verifyBankAccount'])->middleware('rate.limit');
        Route::get('beneficiaries', [FiatController::class, 'beneficiaries']);
        Route::get('virtual-accounts', [FiatController::class, 'virtualAccounts']);
        Route::post('virtual-accounts', [FiatController::class, 'createVirtualAccount'])->middleware('rate.limit');
        Route::post('withdrawals/quote', [FiatController::class, 'withdrawalQuote']);
        Route::post('withdrawals', [FiatController::class, 'createWithdrawal'])->middleware('rate.limit');
        Route::post('withdrawals/{withdrawalId}/submit', [FiatController::class, 'submitWithdrawal'])->middleware('rate.limit');
        Route::get('withdrawals/{withdrawalId}', [FiatController::class, 'withdrawalStatus']);
        Route::get('history', [FiatController::class, 'history']);
        Route::post('pay/intents', [FiatController::class, 'createPayIntent'])->middleware('rate.limit');
        Route::post('pay/intents/{payIntent}/capture', [FiatController::class, 'capturePayIntent'])->middleware('rate.limit');
        Route::get('readiness', [FiatController::class, 'readiness']);
    });

    Route::prefix('rewards')->group(function (): void {
        Route::get('activities', [RewardController::class, 'activities']);
        Route::get('mine', [RewardController::class, 'mine']);
        Route::post('check-in', [RewardController::class, 'checkIn']);
        Route::post('record', [RewardController::class, 'record']);
        Route::post('{rewardId}/claim', [RewardController::class, 'claim']);
    });

    Route::prefix('exapoints')->middleware('throttle:120,1')->group(function (): void {
        Route::get('balance', [ExaPointController::class, 'balance']);
        Route::get('totals', [ExaPointController::class, 'totals']);
        Route::post('spend', [ExaPointController::class, 'spend']);
        Route::post('lock', [ExaPointController::class, 'lock']);
        Route::post('unlock', [ExaPointController::class, 'unlock']);
        Route::post('convert', [ExaPointController::class, 'convert']);
        Route::post('adjust', [ExaPointController::class, 'adjust'])->middleware('role:admin');
        Route::get('admin/summary', [ExaPointController::class, 'adminSummary'])->middleware('role:admin');
        Route::get('admin/users/{userId}/history', [ExaPointController::class, 'adminUserHistory'])->middleware('role:admin');
        Route::get('admin/suspicious', [ExaPointController::class, 'adminSuspicious'])->middleware('role:admin');
    });

    Route::prefix('referrals')->group(function (): void {
        Route::get('summary', [ReferralController::class, 'summary']);
        Route::get('rewards', [ReferralController::class, 'rewards']);
        Route::get('leaderboard', [ReferralController::class, 'leaderboard']);
    });

    Route::prefix('affiliate')->middleware('throttle:120,1')->group(function (): void {
        Route::get('overview', [AffiliateController::class, 'overview']);
        Route::get('referrals', [AffiliateController::class, 'referrals']);
        Route::get('earnings', [AffiliateController::class, 'earnings']);
        Route::get('tools', [AffiliateController::class, 'tools']);
        Route::match(['get', 'post'], 'payouts', [AffiliateController::class, 'payouts'])->middleware('rate.limit');
    });

    Route::prefix('sports')->group(function (): void {
        Route::get('athletes', [SportsController::class, 'athletes']);
        Route::get('athletes/{athleteId}', [SportsController::class, 'athlete']);
        Route::post('athletes/profile', [SportsController::class, 'saveAthleteProfile']);
        Route::get('competitions', [SportsController::class, 'competitions']);
        Route::post('competitions', [SportsController::class, 'createCompetition']);
        Route::post('competitions/{competitionId}/register', [SportsController::class, 'register']);
        Route::post('competitions/{competitionId}/scores', [SportsController::class, 'submitScores']);
        Route::post('competitions/{competitionId}/finalize', [SportsController::class, 'finalize']);
        Route::get('competitions/{competitionId}/leaderboard', [SportsController::class, 'leaderboard']);
        Route::get('athlete-leaderboard', [SportsController::class, 'athleteLeaderboard']);
        Route::post('sponsorships', [SportsController::class, 'createSponsorship']);
        Route::patch('sponsorships/{sponsorshipId}', [SportsController::class, 'updateSponsorship']);
        Route::post('inquiries', [SportsController::class, 'inquiry']);
    });

    Route::prefix('agriculture')->group(function (): void {
        Route::get('projects', [AgriController::class, 'projects']);
        Route::get('projects/{projectId}', [AgriController::class, 'project']);
        Route::post('projects', [AgriController::class, 'createProject']);
        Route::post('projects/{projectId}/invest', [AgriController::class, 'invest']);
        Route::get('investments/mine', [AgriController::class, 'myInvestments']);
        Route::get('farmers', [AgriController::class, 'farmers']);
        Route::post('farmers/apply', [AgriController::class, 'applyFarmer']);
        Route::post('farmers/{farmerId}/review', [AgriController::class, 'reviewFarmer']);
        Route::patch('farmers/{farmerId}/review', [AgriController::class, 'reviewFarmer']);
        Route::post('projects/{projectId}/leases', [AgriController::class, 'createLease']);
        Route::post('projects/{projectId}/produce-updates', [AgriController::class, 'addProduceUpdate']);
        Route::get('projects/{projectId}/produce-feed', [AgriController::class, 'produceFeed']);
        Route::post('projects/{projectId}/evidence', [AgriController::class, 'submitEvidence'])->middleware('throttle:20,1');
        Route::post('projects/{projectId}/settlement', [AgriController::class, 'queueSettlement']);
        Route::post('projects/{projectId}/settlements', [AgriController::class, 'queueSettlement']);
    });

    Route::prefix('giftcard')->group(function (): void {
        Route::get('inventory', [GiftcardController::class, 'inventory']);
        Route::get('orders/mine', [GiftcardController::class, 'myOrders']);
        Route::get('orders/{orderId}', [GiftcardController::class, 'show']);
        Route::post('sell', [GiftcardController::class, 'sell']);
        Route::post('buy', [GiftCardBuyController::class, 'buy']);
        Route::get('orders', [GiftCardBuyController::class, 'getOrders']);
        Route::get('orders/{orderId}/details', [GiftCardBuyController::class, 'getOrder']);
        Route::get('orders/{orderId}/cards', [GiftCardBuyController::class, 'getOrderCards']);
        Route::get('submissions', [GiftcardController::class, 'submissions']);
        Route::get('submissions/{id}', [GiftcardController::class, 'submissionDetails']);
        Route::get('rates', [GiftcardController::class, 'rates']);
        
        // New purchase endpoint with fee management
        Route::post('purchase', [GiftcardController::class, 'purchase']);
        Route::post('{orderId}/refund', [GiftcardController::class, 'refundPurchase']);
        
        Route::get('admin/review-queue', [GiftcardController::class, 'reviewQueue']);
        Route::post('admin/orders/{orderId}/decision', [GiftcardController::class, 'decide']);
        Route::post('admin/submissions/{submissionId}/approve', [GiftcardController::class, 'approveSubmission']);
        Route::post('admin/submissions/{submissionId}/reject', [GiftcardController::class, 'rejectSubmission']);
        
        // Admin revenue and fee reporting
        Route::get('admin/revenue-summary', [GiftcardController::class, 'getRevenueSummary']);
        Route::get('admin/fee-report', [GiftcardController::class, 'getFeeReport']);
    });

    Route::prefix('admin/giftcard')->group(function (): void {
        Route::get('inventory', [AdminGiftCardBuyController::class, 'getInventory']);
        Route::post('inventory/bulk-upload', [AdminGiftCardBuyController::class, 'uploadInventory']);
        Route::get('buy-orders', [AdminGiftCardBuyController::class, 'getPurchaseOrders']);
        Route::post('buy-orders/{orderId}/approve', [AdminGiftCardBuyController::class, 'approvePurchase']);
        Route::post('buy-orders/{orderId}/reject', [AdminGiftCardBuyController::class, 'rejectPurchase']);
        Route::get('pricing-rates', [AdminGiftCardBuyController::class, 'getPricingRates']);
        Route::put('pricing-rates/{id}', [AdminGiftCardBuyController::class, 'updatePricingRate']);
    });

    Route::prefix('p2p')->group(function (): void {
        Route::get('meta', [P2PController::class, 'meta']);
        Route::get('payment-methods', [P2PController::class, 'paymentMethods']);
        Route::post('payment-methods', [P2PController::class, 'createPaymentMethod'])->middleware('rate.limit');
        Route::patch('payment-methods/{paymentMethodId}', [P2PController::class, 'updatePaymentMethod'])->middleware('rate.limit');
        Route::delete('payment-methods/{paymentMethodId}', [P2PController::class, 'deletePaymentMethod'])->middleware('rate.limit');
        Route::get('ads', [P2PController::class, 'ads']);
        Route::get('ads/mine', [P2PController::class, 'myAds']);
        Route::post('ads', [P2PController::class, 'createAd']);
        Route::post('ads/{adId}/trades', [P2PController::class, 'openTrade']);
        Route::get('trades/mine', [P2PController::class, 'myTrades']);
        Route::get('trades/{tradeUuid}', [P2PController::class, 'showTrade']);
        Route::post('trades/{tradeUuid}/payment-proof', [P2PController::class, 'uploadPaymentProof'])->middleware('rate.limit');
        Route::get('trades/{tradeUuid}/payment-proof', [P2PController::class, 'paymentProof'])->name('p2p.payment-proof');
        Route::post('trades/{tradeUuid}/payment-sent', [P2PController::class, 'markPaymentSent']);
        Route::post('trades/{tradeUuid}/release', [P2PController::class, 'release']);
        Route::post('trades/{tradeUuid}/cancel', [P2PController::class, 'cancel']);
        Route::get('trades/{tradeUuid}/messages', [P2PController::class, 'messages']);
        Route::post('trades/{tradeUuid}/messages', [P2PController::class, 'sendMessage']);
        Route::post('trades/{tradeUuid}/disputes', [P2PController::class, 'openDispute']);
        Route::get('admin/disputes', [P2PController::class, 'reviewQueue']);
        Route::post('admin/disputes/{disputeId}/resolve', [P2PController::class, 'resolveDispute']);
        Route::post('trades/{tradeUuid}/rate', [P2PController::class, 'rateTrade']);
    });

    Route::prefix('v1/p2p')->group(function (): void {
        Route::get('meta', [P2PController::class, 'meta']);
        Route::get('ads', [P2PController::class, 'ads']);
        Route::post('ads', [P2PController::class, 'createAd']);
        Route::get('ads/mine', [P2PController::class, 'myAds']);
        Route::patch('ads/{adId}', [P2PController::class, 'updateAdStatus']);
        Route::post('ads/{adId}/pause', [P2PController::class, 'pauseAd']);
        Route::post('ads/{adId}/resume', [P2PController::class, 'resumeAd']);
        Route::post('orders', [P2PController::class, 'openOrder']);
        Route::post('ads/{adId}/orders', [P2PController::class, 'openTrade']);
        Route::get('orders', [P2PController::class, 'myTrades']);
        Route::get('orders/{tradeUuid}', [P2PController::class, 'showTrade']);
        Route::post('orders/{tradeUuid}/mark-paid', [P2PController::class, 'markPaymentSent']);
        Route::post('orders/{tradeUuid}/release', [P2PController::class, 'release']);
        Route::post('orders/{tradeUuid}/cancel', [P2PController::class, 'cancel']);
        Route::post('orders/{tradeUuid}/dispute', [P2PController::class, 'openDispute']);
        Route::post('orders/{tradeUuid}/evidence', [P2PController::class, 'uploadPaymentProof'])->middleware('rate.limit');
        Route::post('orders/{tradeUuid}/feedback', [P2PController::class, 'rateTrade']);
        Route::get('payment-methods', [P2PController::class, 'paymentMethods']);
        Route::post('payment-methods', [P2PController::class, 'createPaymentMethod'])->middleware('rate.limit');
    });

    Route::prefix('games/flight')->group(function (): void {
        Route::get('my-bets', [FlightGameController::class, 'myBets']);
        Route::post('bets', [FlightGameController::class, 'placeBet'])->middleware('rate.limit');
        Route::post('bets/{betUuid}/cashout', [FlightGameController::class, 'cashOut'])->middleware('rate.limit');
        Route::post('responsible-gaming/self-exclusion', [FlightGameController::class, 'selfExclude'])->middleware('rate.limit');
    });
    Route::prefix('gamefi')->group(function (): void {
        Route::get('lotteries', [GameFiController::class, 'lotteryGames']);
        Route::get('lotteries/{gameId}', [GameFiController::class, 'lotteryGame']);
        Route::post('lotteries', [GameFiController::class, 'createLotteryGame']);
        Route::post('lottery/enter', [GameFiController::class, 'enterLottery']);
        Route::post('lotteries/{gameId}/join', [GameFiController::class, 'joinLottery']);
        Route::get('betting-pools', [GameFiController::class, 'bettingPools']);
        Route::post('betting-pools', [GameFiController::class, 'createBettingPool']);
        Route::post('betting-pools/{poolId}/bets', [GameFiController::class, 'placeBet']);
        Route::post('betting-pools/{poolId}/resolve', [GameFiController::class, 'resolveBettingPool']);
    });

    Route::prefix('nft')->group(function (): void {
        Route::get('dashboard', [NftController::class, 'dashboard']);
        Route::get('collections', [NftController::class, 'collections']);
        Route::get('marketplace', [NftController::class, 'marketplace']);
        Route::get('my-assets', [NftController::class, 'myNfts']);
        Route::post('collections', [NftController::class, 'createCollection']);
        Route::post('media', [NftController::class, 'uploadMedia'])->middleware('rate.limit');
        Route::get('media/{mediaAsset}/private-url', [NftController::class, 'privateMedia'])->middleware('rate.limit');
        Route::post('mint', [NftController::class, 'mint']);
        Route::post('assets/{nftId}/upgrade', [NftController::class, 'upgrade']);
        Route::post('assets/{nftId}/subscriptions', [NftController::class, 'subscribe']);
        Route::post('assets/{nftId}/listings', [NftController::class, 'createListing']);
        Route::post('assets/{nftId}/reports', [NftController::class, 'report'])->middleware('rate.limit');
        Route::post('reports/{report}/evidence', [NftController::class, 'uploadReportEvidence'])->middleware('rate.limit');
        Route::post('listings/{listingId}/buy', [NftController::class, 'buyListing']);
        Route::post('assets/{nftId}/auctions', [NftController::class, 'createAuction']);
        Route::post('auctions/{auctionId}/bids', [NftController::class, 'bid']);
        Route::post('auctions/{auctionId}/finalize', [NftController::class, 'finalizeAuction']);
    });

    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread', [NotificationController::class, 'unread']);
        Route::get('stats', [NotificationController::class, 'stats']);
        Route::match(['get', 'put'], 'preferences', [NotificationController::class, 'preferences']);
        Route::get('{notification}', [NotificationController::class, 'show']);
        Route::put('{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('mark-all-read', [NotificationController::class, 'markAllAsRead']);
        Route::delete('{notification}', [NotificationController::class, 'destroy']);
        Route::delete('/', [NotificationController::class, 'deleteAll']);

        // Device token management
        Route::post('device-tokens', [NotificationController::class, 'registerDeviceToken']);
        Route::get('device-tokens', [NotificationController::class, 'getDeviceTokens']);
        Route::delete('device-tokens/{deviceToken}', [NotificationController::class, 'deactivateDeviceToken']);
        Route::post('device-tokens/deactivate-all', [NotificationController::class, 'deactivateAllDeviceTokens']);
    });

    Route::prefix('activity-center')->group(function (): void {
        Route::get('/', [UnifiedActivityCenterController::class, 'index']);
        Route::get('notifications', [UnifiedActivityCenterController::class, 'notifications']);
        Route::get('activity', [UnifiedActivityCenterController::class, 'activity']);
    });

    Route::prefix('v1/support')->middleware('throttle:30,1')->group(function (): void {
        Route::get('meta', [SupportController::class, 'meta']);
        Route::get('knowledge-base', [SupportController::class, 'kb']);
        Route::get('chat/availability', [SupportLiveChatController::class, 'availability']);
        Route::post('chat/conversations', [SupportLiveChatController::class, 'start'])->middleware('rate.limit');
        Route::post('chat/conversations/{conversation}/messages', [SupportLiveChatController::class, 'messages'])->middleware('rate.limit');
        Route::get('chat/conversations/{conversation}/replay', [SupportLiveChatController::class, 'replay']);
        Route::post('chat/conversations/{conversation}/end', [SupportLiveChatController::class, 'end'])->middleware('rate.limit');
        Route::get('tickets', [SupportController::class, 'index']);
        Route::post('tickets', [SupportController::class, 'store'])->middleware('rate.limit');
        Route::get('tickets/{ticket}', [SupportController::class, 'show']);
        Route::post('tickets/{ticket}/messages', [SupportController::class, 'message'])->middleware('rate.limit');
        Route::post('tickets/{ticket}/attachments', [SupportController::class, 'attach'])->middleware('rate.limit');
        Route::post('tickets/{ticket}/close', [SupportController::class, 'close'])->middleware('rate.limit');
        Route::post('tickets/{ticket}/reopen', [SupportController::class, 'reopen'])->middleware('rate.limit');
    });

    Route::prefix('kyc')->middleware('throttle:30,1')->group(function (): void {
        Route::post('upload', [KycController::class, 'upload']);
    });

    Route::prefix('admin/settings')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('/', [AdminSettingController::class, 'index']);
        Route::put('{key}', [AdminSettingController::class, 'update']);
        Route::get('treasury', [AdminSettingController::class, 'treasurySettings']);
        Route::post('treasury/config', [AdminSettingController::class, 'updateTreasuryConfig']);
        Route::post('treasury/wallets/{id}/update-key', [AdminSettingController::class, 'updateWalletKey']);
        Route::post('treasury/wallets/{id}/update-address', [AdminSettingController::class, 'updateWalletAddress']);
    });

    Route::prefix('admin/kyc')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('flagged', [KycAdminController::class, 'flagged']);
        Route::get('{id}', [KycAdminController::class, 'show']);
        Route::post('approve', [KycAdminController::class, 'approve']);
        Route::post('reject', [KycAdminController::class, 'reject']);
    });

    Route::prefix('admin/ai-intel')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::post('market-data', [AIIntelligenceController::class, 'ingest']);
        Route::get('dashboard', [AIIntelligenceController::class, 'dashboard']);
        Route::get('alerts', [AIIntelligenceController::class, 'alerts']);
        Route::post('override', [AIIntelligenceController::class, 'override']);
        Route::post('run-loop', [AIIntelligenceController::class, 'runLoop']);
    });

    Route::prefix('admin/market-maker')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('dashboard', [MarketMakerAdminController::class, 'dashboard']);
        Route::get('configs', [MarketMakerAdminController::class, 'configs']);
        Route::post('configs', [MarketMakerAdminController::class, 'upsertConfig']);
        Route::post('run-loop', [MarketMakerAdminController::class, 'runLoop']);
        Route::post('run/{symbol}', [MarketMakerAdminController::class, 'runSymbol']);
        Route::get('pools', [MarketMakerAdminController::class, 'pools']);
        Route::post('pools/add', [MarketMakerAdminController::class, 'addLiquidity']);
        Route::post('pools/remove', [MarketMakerAdminController::class, 'removeLiquidity']);
        Route::get('alerts', [MarketMakerAdminController::class, 'alerts']);
    });

    Route::prefix('admin/sor')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('summary', [SmartOrderRoutingAdminController::class, 'summary']);
        Route::get('executions', [SmartOrderRoutingAdminController::class, 'executions']);
    });

    Route::prefix('admin/treasury')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('wallets', [TreasuryController::class, 'wallets']);
        Route::post('wallets', [TreasuryController::class, 'createWallet']);
        Route::get('balances', [TreasuryController::class, 'balances']);
        Route::post('move-to-cold', [TreasuryController::class, 'moveToCold']);
        Route::post('move-to-hot', [TreasuryController::class, 'moveToHot']);
        Route::get('withdraw-requests', [TreasuryController::class, 'withdrawRequests']);
        Route::post('withdraw-requests/{id}/approve', [TreasuryController::class, 'approveWithdraw']);
        Route::post('withdraw-requests/{id}/sign', [TreasuryController::class, 'signWithdraw']);
        Route::get('transactions', [TreasuryController::class, 'transactions']);

        // Monitoring
        Route::get('monitoring/status', [TreasuryMonitoringController::class, 'monitoringStatus']);
        Route::get('monitoring/health', [TreasuryMonitoringController::class, 'healthCheck']);
        Route::post('monitoring/watch', [TreasuryMonitoringController::class, 'startWatching']);
        Route::post('monitoring/unwatch', [TreasuryMonitoringController::class, 'stopWatching']);
    });

    Route::prefix('admin/v1/custody')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('overview', [CustodyOperationsController::class, 'overview']);
        Route::get('networks', [CustodyOperationsController::class, 'networks']);
        Route::get('wallets', [CustodyOperationsController::class, 'wallets']);
        Route::get('deposits', [CustodyOperationsController::class, 'deposits']);
        Route::get('withdrawals', [CustodyOperationsController::class, 'withdrawals']);
        Route::get('reconciliation', [CustodyOperationsController::class, 'reconciliation']);
        Route::get('hot-wallets', [CustodyOperationsController::class, 'hotWallets']);
        Route::get('withdrawal-reserves', [CustodyOperationsController::class, 'withdrawalReserves']);
        Route::get('network-fees', [CustodyOperationsController::class, 'networkFees']);
        Route::get('signers', [CustodyOperationsController::class, 'signers']);
        Route::post('withdrawals/{withdrawalId}/approve', [CustodyOperationsController::class, 'approveWithdrawal'])->middleware('rate.limit');
        Route::post('sweeps/evaluate', [CustodyOperationsController::class, 'runSweep'])->middleware('rate.limit');
    });

    Route::prefix('ai')->group(function (): void {
        // Profile management
        Route::get('profile', [AITradingAssistantController::class, 'getProfile']);
        Route::post('profile/init', [AITradingAssistantController::class, 'initializeProfile']);
        Route::patch('profile', [AITradingAssistantController::class, 'updateProfile']);

        // Trading signals
        Route::get('signals', [AITradingAssistantController::class, 'getSignals']);
        Route::get('signals/{signal}', [AITradingAssistantController::class, 'getSignal']);
        Route::post('signals/generate', [AITradingAssistantController::class, 'generateSignal']);

        // Risk management
        Route::get('risk-assessment', [AITradingAssistantController::class, 'getRiskAssessment']);
        Route::post('validate-trade', [AITradingAssistantController::class, 'validateTrade']);

        // AI Assistant chat
        Route::post('assistant/chat', [AITradingAssistantController::class, 'chat']);
        Route::get('assistant/conversations', [AITradingAssistantController::class, 'listConversations']);
        Route::get('assistant/conversations/{id}', [AITradingAssistantController::class, 'getConversation']);

        // Recommendations
        Route::get('recommendations', [AITradingAssistantController::class, 'getRecommendations']);

        // Auto-trading strategies
        Route::get('strategies', [AITradingAssistantController::class, 'listStrategies']);
        Route::post('strategies', [AITradingAssistantController::class, 'createStrategy']);
        Route::patch('strategies/{strategy}', [AITradingAssistantController::class, 'updateStrategy']);
        Route::post('strategies/{strategy}/activate', [AITradingAssistantController::class, 'activateStrategy']);
        Route::post('strategies/{strategy}/deactivate', [AITradingAssistantController::class, 'deactivateStrategy']);
        Route::get('strategies/{strategy}/metrics', [AITradingAssistantController::class, 'getStrategyMetrics']);
        Route::delete('strategies/{strategy}', [AITradingAssistantController::class, 'deleteStrategy']);
    });


    Route::prefix('exaai')->group(function (): void {
        Route::get('overview', [ExaAiController::class, 'overview']);
        Route::get('plans', [ExaAiController::class, 'plans']);
        Route::get('subscription', [ExaAiController::class, 'subscription']);
        Route::post('subscription', [ExaAiController::class, 'subscribe'])->middleware('rate.limit');
        Route::get('strategies', [ExaAiController::class, 'strategies']);
        Route::get('allocations', [ExaAiController::class, 'allocations']);
        Route::get('allocations/active', [ExaAiController::class, 'activeAllocation']);
        Route::post('allocations', [ExaAiController::class, 'allocationStore'])->middleware('rate.limit');
        Route::post('sessions', [ExaAiController::class, 'sessionStore'])->middleware('rate.limit');
        Route::get('sessions/current', [ExaAiController::class, 'sessionCurrent']);
        Route::post('sessions/{id}/pause', [ExaAiController::class, 'pause'])->middleware('rate.limit');
        Route::post('sessions/{id}/resume', [ExaAiController::class, 'resume'])->middleware('rate.limit');
        Route::post('sessions/{id}/stop', [ExaAiController::class, 'stop'])->middleware('rate.limit');
        Route::get('positions', [ExaAiController::class, 'positions']);
        Route::get('trades', [ExaAiController::class, 'trades']);
        Route::get('performance', [ExaAiController::class, 'performance']);
        Route::post('terms/accept', [ExaAiController::class, 'acceptTerms'])->middleware('rate.limit');
        Route::get('portfolio', [ExaAiController::class, 'portfolio']);
        Route::post('decisions', [ExaAiController::class, 'decisionStore'])->middleware('rate.limit');
        Route::post('decisions/{id}/execute', [ExaAiController::class, 'decisionExecute'])->middleware('rate.limit');
        Route::get('realtime/replay', [ExaAiController::class, 'realtimeReplay']);
        Route::get('readiness', [ExaAiController::class, 'readiness']);
    });
    Route::prefix('admin/exaai')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('overview', [ExaAiAdminController::class, 'overview']);
        Route::get('plans', [ExaAiAdminController::class, 'plans']);
        Route::patch('plans/{id}/entitlements', [ExaAiAdminController::class, 'updatePlanEntitlements']);
        Route::get('users/{id}/entitlements', [ExaAiAdminController::class, 'userEntitlements']);
        Route::get('strategies', [ExaAiAdminController::class, 'strategies']);
        Route::get('sessions', [ExaAiAdminController::class, 'sessions']);
        Route::get('subscriptions', [ExaAiAdminController::class, 'subscriptions']);
        Route::get('trades', [ExaAiAdminController::class, 'trades']);
        Route::get('audit-logs', [ExaAiAdminController::class, 'auditLogs']);
        Route::get('readiness', [ExaAiAdminController::class, 'readiness']);
        Route::get('operations/readiness', [ExaAiAdminController::class, 'operationsReadiness']);
        Route::post('market-eligibility', [ExaAiAdminController::class, 'marketEligibilityStore']);
        Route::post('controls', [ExaAiAdminController::class, 'controls']);
        Route::get('surveillance-cases', [ExaAiAdminController::class, 'surveillanceCases']);
        Route::post('operations/safe-resume', [ExaAiAdminController::class, 'safeResume']);
        Route::post('operations/expire-stale-decisions', [ExaAiAdminController::class, 'expireStaleDecisions']);
        Route::post('operations/auto-disable-markets', [ExaAiAdminController::class, 'autoDisableMarkets']);
        Route::post('strategies/versions/{id}/transition', [ExaAiAdminController::class, 'transitionStrategy']);
    });
    Route::prefix('admin/giftcard')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('center', [GiftCardAdminController::class, 'center']);
        Route::get('submissions', [GiftCardAdminController::class, 'submissions']);
        Route::get('submissions/{id}', [GiftCardAdminController::class, 'submissionDetails']);
        Route::post('submissions/{id}/approve', [GiftCardAdminController::class, 'approve']);
        Route::post('submissions/{id}/reject', [GiftCardAdminController::class, 'reject']);
        Route::get('stats', [GiftCardAdminController::class, 'stats']);
    });

    Route::prefix('admin/exapay')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('overview', [ExaPayOperationsController::class, 'overview']);
        Route::get('merchants', [ExaPayOperationsController::class, 'merchants']);
        Route::post('merchants/{merchantId}/approve', [ExaPayOperationsController::class, 'approve'])->middleware('rate.limit');
        Route::post('merchants/{merchantId}/restrict', [ExaPayOperationsController::class, 'restrict'])->middleware('rate.limit');
        Route::get('reconciliation', [ExaPayOperationsController::class, 'reconciliation']);
        Route::get('reports', [ExaPayOperationsController::class, 'reports']);
    });

    Route::prefix('admin/security')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('dashboard', [SecurityController::class, 'getDashboard']);
        Route::get('events', [SecurityController::class, 'getEvents']);
        Route::get('events/user/{userId}', [SecurityController::class, 'getUserEvents']);
        Route::get('events/ip/{ip}', [SecurityController::class, 'getIPEvents']);
        
        Route::get('blocked-ips', [SecurityController::class, 'getBlockedIPs']);
        Route::post('block-ip', [SecurityController::class, 'blockIP']);
        Route::post('unblock-ip', [SecurityController::class, 'unblockIP']);
        Route::post('whitelist-ip', [SecurityController::class, 'whitelistIP']);
        Route::post('blacklist-ip', [SecurityController::class, 'blacklistIP']);
        
        Route::post('unflag-identifier', [SecurityController::class, 'unflagIdentifier']);
        
        Route::get('settings', [SecurityController::class, 'getSettings']);
        Route::put('settings', [SecurityController::class, 'updateSettings']);
    });

    Route::prefix('admin')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::prefix('pairs')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'pairs'])->middleware('check.permission:trade.manage');
            Route::post('/', [AdminPlatformController::class, 'createPair'])->middleware(['check.permission:trade.manage', 'rate.limit']);
            Route::put('/', [AdminPlatformController::class, 'updatePair'])->middleware(['check.permission:trade.manage', 'rate.limit']);
            Route::post('disable', [AdminPlatformController::class, 'disablePair'])->middleware(['check.permission:trade.manage', 'rate.limit']);
        });

        Route::get('orders', [AdminPlatformController::class, 'orders'])->middleware('check.permission:trade.manage');
        Route::get('trades', [AdminPlatformController::class, 'trades'])->middleware('check.permission:trade.manage');

        Route::prefix('rewards')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'rewards'])->middleware('check.permission:reward.manage');
            Route::post('/', [AdminPlatformController::class, 'upsertReward'])->middleware(['check.permission:reward.manage', 'rate.limit']);
            Route::put('/', [AdminPlatformController::class, 'upsertReward'])->middleware(['check.permission:reward.manage', 'rate.limit']);
            Route::delete('{id}', [AdminPlatformController::class, 'deleteReward'])->middleware(['check.permission:reward.manage', 'rate.limit']);
        });

        Route::prefix('staking/pools')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'stakingPools'])->middleware('check.permission:staking.manage');
            Route::post('/', [AdminPlatformController::class, 'upsertStakingPool'])->middleware(['check.permission:staking.manage', 'rate.limit']);
            Route::put('/', [AdminPlatformController::class, 'upsertStakingPool'])->middleware(['check.permission:staking.manage', 'rate.limit']);
            Route::post('disable', [AdminPlatformController::class, 'disableStakingPool'])->middleware(['check.permission:staking.manage', 'rate.limit']);
        });

        Route::prefix('users')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'users'])->middleware('check.permission:users.view');
            Route::get('profile-images/review', [AdminPlatformController::class, 'profileImageReviewQueue'])->middleware('check.permission:users.view');
            Route::get('{id}/profile-identity', [AdminPlatformController::class, 'userProfileIdentity'])->middleware('check.permission:users.view');
            Route::post('{id}/profile-image/remove', [AdminPlatformController::class, 'removeUserProfileImage'])->middleware(['check.permission:users.edit', 'rate.limit']);
            Route::post('{id}/profile-image/suspend', [AdminPlatformController::class, 'suspendUserProfileImages'])->middleware(['check.permission:users.edit', 'rate.limit']);
            Route::get('{id}', [AdminPlatformController::class, 'user'])->middleware('check.permission:users.view');
            Route::post('freeze', [AdminPlatformController::class, 'freezeUser'])->middleware('check.permission:users.edit');
            Route::post('unfreeze', [AdminPlatformController::class, 'unfreezeUser'])->middleware('check.permission:users.edit');
            Route::post('adjust-balance', [AdminPlatformController::class, 'adjustUserBalance'])->middleware('check.permission:wallet.adjust');
            Route::get('logs', [AdminPlatformController::class, 'userLogs'])->middleware('check.permission:logs.view');
            Route::get('wallets', [AdminPlatformController::class, 'userWallets'])->middleware('check.permission:users.view');
            Route::get('trades', [AdminPlatformController::class, 'userTrades'])->middleware('check.permission:trade.manage');
            Route::get('rewards', [AdminPlatformController::class, 'userRewards'])->middleware('check.permission:reward.manage');
        });

        Route::prefix('wallets')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'wallets'])->middleware('check.permission:users.view');
            Route::post('freeze', [AdminPlatformController::class, 'freezeWallet'])->middleware('check.permission:wallet.adjust');
            Route::post('adjust', [AdminPlatformController::class, 'adjustWallet'])->middleware('check.permission:wallet.adjust');
        });

        Route::get('transactions', [AdminPlatformController::class, 'transactions'])->middleware('check.permission:logs.view');

        Route::prefix('treasury')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'treasury'])->middleware('check.permission:treasury.manage');
            Route::post('move', [AdminPlatformController::class, 'treasuryMove'])->middleware('check.permission:treasury.manage');
            Route::post('approve-withdraw', [AdminPlatformController::class, 'approveWithdraw'])->middleware('check.permission:treasury.manage');
            Route::get('logs', [AdminPlatformController::class, 'treasuryLogs'])->middleware('check.permission:logs.view');
        });

        Route::get('logs', [AdminPlatformController::class, 'logs'])->middleware('check.permission:logs.view');
        Route::get('admin-logs', [AdminPlatformController::class, 'adminLogs'])->middleware('check.permission:logs.view');
        Route::get('security-logs', [AdminPlatformController::class, 'securityLogs'])->middleware('check.permission:logs.view');

        Route::prefix('notifications')->group(function (): void {
            Route::post('send', [AdminPlatformController::class, 'sendNotification'])->middleware('check.permission:notifications.send');
            Route::get('/', [AdminPlatformController::class, 'notifications'])->middleware('check.permission:notifications.send');
            Route::get('operations/overview', [NotificationOperationsController::class, 'overview'])->middleware('check.permission:notifications.view');
            Route::get('operations/events', [NotificationOperationsController::class, 'events'])->middleware('check.permission:notifications.templates');
            Route::get('operations/deliveries', [NotificationOperationsController::class, 'deliveries'])->middleware('check.permission:notifications.view');
            Route::get('operations/dlq', [NotificationOperationsController::class, 'dlq'])->middleware('check.permission:notifications.dlq');
            Route::post('operations/deliveries/{delivery}/retry', [NotificationOperationsController::class, 'retry'])->middleware('check.permission:notifications.dlq');
            Route::get('operations/providers', [NotificationOperationsController::class, 'providers'])->middleware('check.permission:notifications.providers');
            Route::get('operations/templates', [NotificationOperationsController::class, 'templates'])->middleware('check.permission:notifications.templates');
            Route::post('operations/templates/preview', [NotificationOperationsController::class, 'preview'])->middleware('check.permission:notifications.templates');
        });

        Route::prefix('support')->group(function (): void {
            Route::get('overview', [SupportOperationsController::class, 'overview'])->middleware('check.permission:support.view');
            Route::get('tickets', [SupportOperationsController::class, 'tickets'])->middleware('check.permission:support.view');
            Route::get('tickets/{ticket}', [SupportOperationsController::class, 'show'])->middleware('check.permission:support.view');
            Route::post('tickets/{ticket}/reply', [SupportOperationsController::class, 'reply'])->middleware(['check.permission:support.reply', 'rate.limit']);
            Route::post('tickets/{ticket}/assign', [SupportOperationsController::class, 'assign'])->middleware('check.permission:support.assign');
            Route::post('tickets/{ticket}/transition', [SupportOperationsController::class, 'transition'])->middleware('check.permission:support.resolve');
            Route::post('tickets/{ticket}/escalate', [SupportOperationsController::class, 'escalate'])->middleware('check.permission:support.escalate');
            Route::get('disputes', [SupportOperationsController::class, 'disputes'])->middleware('check.permission:support.view');
            Route::match(['get', 'post'], 'knowledge-base', [SupportOperationsController::class, 'knowledgeBase'])->middleware('check.permission:support.manage_kb');
            Route::match(['get', 'put'], 'live-chat/settings', [SupportLiveChatOperationsController::class, 'settings'])->middleware('check.permission:support.live_chat.manage');
            Route::match(['get', 'post'], 'live-chat/agents', [SupportLiveChatOperationsController::class, 'agents'])->middleware('check.permission:support.live_chat.manage');
            Route::post('live-chat/heartbeat', [SupportLiveChatOperationsController::class, 'heartbeat'])->middleware('check.permission:support.live_chat.agent');
            Route::get('live-chat/conversations', [SupportLiveChatOperationsController::class, 'conversations'])->middleware('check.permission:support.live_chat.view');
            Route::get('live-chat/conversations/{conversation}/replay', [SupportLiveChatOperationsController::class, 'replay'])->middleware('check.permission:support.live_chat.view');
            Route::post('live-chat/conversations/{conversation}/messages', [SupportLiveChatOperationsController::class, 'message'])->middleware(['check.permission:support.live_chat.agent', 'rate.limit']);
            Route::post('live-chat/conversations/{conversation}/assign', [SupportLiveChatOperationsController::class, 'assign'])->middleware('check.permission:support.live_chat.manage');
            Route::post('live-chat/conversations/{conversation}/transfer', [SupportLiveChatOperationsController::class, 'transfer'])->middleware('check.permission:support.live_chat.manage');
            Route::post('live-chat/conversations/{conversation}/end', [SupportLiveChatOperationsController::class, 'end'])->middleware('check.permission:support.live_chat.agent');
            Route::post('live-chat/conversations/{conversation}/convert-to-ticket', [SupportLiveChatOperationsController::class, 'convert'])->middleware('check.permission:support.live_chat.agent');
            Route::match(['get', 'post'], 'live-chat/canned-responses', [SupportLiveChatOperationsController::class, 'canned'])->middleware('check.permission:support.live_chat.manage');
            Route::get('live-chat/health', [SupportLiveChatOperationsController::class, 'health'])->middleware('check.permission:support.live_chat.view');
        });

        Route::prefix('settings')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'settings'])->middleware('check.permission:settings.manage');
            Route::put('/', [AdminPlatformController::class, 'updateSettings'])->middleware('check.permission:settings.manage');
        });

        Route::get('admins', [AdminPlatformController::class, 'admins'])->middleware('check.permission:admins.manage');
        Route::post('admins', [AdminPlatformController::class, 'createAdmin'])->middleware(['check.permission:admins.manage', 'rate.limit']);
        Route::get('roles', [AdminPlatformController::class, 'roles'])->middleware('check.permission:roles.manage');
        Route::post('roles', [AdminPlatformController::class, 'upsertRole'])->middleware(['check.permission:roles.manage', 'rate.limit']);

        Route::prefix('nft')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'nft')->middleware('check.permission:nft.manage');
            Route::post('approve', [AdminPlatformController::class, 'modelStore'])->defaults('resource', 'nft')->middleware(['check.permission:nft.manage', 'rate.limit']);
            Route::post('remove', [AdminPlatformController::class, 'modelDisable'])->defaults('resource', 'nft')->middleware(['check.permission:nft.manage', 'rate.limit']);
            Route::get('sales', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'nft-sales')->middleware('check.permission:nft.manage');
            Route::get('operations/overview', [NftOperationsController::class, 'overview'])->middleware('check.permission:nft.manage');
            Route::get('operations/media', [NftOperationsController::class, 'media'])->middleware('check.permission:nft.manage');
            Route::post('operations/media/{mediaAsset}/review', [NftOperationsController::class, 'reviewMedia'])->middleware(['check.permission:nft.manage', 'rate.limit']);
            Route::get('operations/media/reconciliation', [NftOperationsController::class, 'mediaReconciliation'])->middleware('check.permission:nft.manage');
            Route::get('operations/storage-health', [NftOperationsController::class, 'storageHealth'])->middleware('check.permission:nft.manage');
            Route::get('operations/reports', [NftOperationsController::class, 'reports'])->middleware('check.permission:nft.manage');
            Route::post('operations/reports/{report}/review', [NftOperationsController::class, 'reviewReport'])->middleware(['check.permission:nft.manage', 'rate.limit']);
            Route::get('operations/reconciliation', [NftOperationsController::class, 'reconciliation'])->middleware('check.permission:nft.manage');
        });

        Route::prefix('agri/projects')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'agri-projects')->middleware('check.permission:agri.manage');
            Route::post('/', [AdminPlatformController::class, 'modelStore'])->defaults('resource', 'agri-projects')->middleware(['check.permission:agri.manage', 'rate.limit']);
            Route::put('/', [AdminPlatformController::class, 'modelUpdate'])->defaults('resource', 'agri-projects')->middleware(['check.permission:agri.manage', 'rate.limit']);
            Route::post('close', [AdminPlatformController::class, 'modelDisable'])->defaults('resource', 'agri-projects')->middleware(['check.permission:agri.manage', 'rate.limit']);
        });

        Route::prefix('agri/operations')->middleware('check.permission:agri.manage')->group(function (): void {
            Route::get('summary', [AgriTechOperationsController::class, 'summary']);
            Route::get('evidence', [AgriTechOperationsController::class, 'evidence']);
            Route::post('evidence/{evidenceId}/review', [AgriTechOperationsController::class, 'reviewEvidence'])->middleware('rate.limit');
            Route::post('milestones', [AgriTechOperationsController::class, 'createMilestone'])->middleware('rate.limit');
            Route::post('projects/{projectId}/transition', [AgriTechOperationsController::class, 'transitionProject'])->middleware('rate.limit');
            Route::post('milestones/{milestoneId}/approve', [AgriTechOperationsController::class, 'approveMilestone'])->middleware('rate.limit');
            Route::post('disbursements', [AgriTechOperationsController::class, 'requestDisbursement'])->middleware('rate.limit');
            Route::post('disbursements/{id}/approve', [AgriTechOperationsController::class, 'approveDisbursement'])->middleware('rate.limit');
            Route::post('disbursements/{id}/settle', [AgriTechOperationsController::class, 'settleDisbursement'])->middleware('rate.limit');
            Route::get('reconciliation', [AgriTechOperationsController::class, 'reconciliation']);
            Route::post('investments/{investmentId}/refund', [AgriTechOperationsController::class, 'refund'])->middleware('rate.limit');
        });

        Route::prefix('sports')->group(function (): void {
            Route::get('athletes', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'sports-athletes')->middleware('check.permission:sports.manage');
            Route::post('approve', [AdminPlatformController::class, 'modelUpdate'])->defaults('resource', 'sports-athletes')->middleware(['check.permission:sports.manage', 'rate.limit']);
            Route::get('rewards', [AdminPlatformController::class, 'rewards'])->middleware('check.permission:sports.manage');
        });

        Route::prefix('courses')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'courses')->middleware('check.permission:edtech.manage');
            Route::post('/', [AdminPlatformController::class, 'modelStore'])->defaults('resource', 'courses')->middleware(['check.permission:edtech.manage', 'rate.limit']);
            Route::put('/', [AdminPlatformController::class, 'modelUpdate'])->defaults('resource', 'courses')->middleware(['check.permission:edtech.manage', 'rate.limit']);
            Route::delete('/', [AdminPlatformController::class, 'modelDisable'])->defaults('resource', 'courses')->middleware(['check.permission:edtech.manage', 'rate.limit']);
        });

        Route::get('certificates', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'courses')->middleware('check.permission:edtech.manage');

        Route::prefix('campaigns')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'campaigns')->middleware('check.permission:campaign.manage');
            Route::post('/', [AdminPlatformController::class, 'modelStore'])->defaults('resource', 'campaigns')->middleware(['check.permission:campaign.manage', 'rate.limit']);
            Route::put('/', [AdminPlatformController::class, 'modelUpdate'])->defaults('resource', 'campaigns')->middleware(['check.permission:campaign.manage', 'rate.limit']);
            Route::post('close', [AdminPlatformController::class, 'modelDisable'])->defaults('resource', 'campaigns')->middleware(['check.permission:campaign.manage', 'rate.limit']);
        });

        Route::prefix('lottery')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'lottery')->middleware('check.permission:lottery.manage');
            Route::post('/', [AdminPlatformController::class, 'modelStore'])->defaults('resource', 'lottery')->middleware(['check.permission:lottery.manage', 'rate.limit']);
            Route::post('draw', [AdminPlatformController::class, 'modelStore'])->defaults('resource', 'lottery-winners')->middleware(['check.permission:lottery.manage', 'rate.limit']);
            Route::get('winners', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'lottery-winners')->middleware('check.permission:lottery.manage');
        });

        Route::prefix('giftcards')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'giftcards')->middleware('check.permission:giftcard.manage');
            Route::post('/', [AdminPlatformController::class, 'modelStore'])->defaults('resource', 'giftcards')->middleware(['check.permission:giftcard.manage', 'rate.limit']);
            Route::put('/', [AdminPlatformController::class, 'modelUpdate'])->defaults('resource', 'giftcards')->middleware(['check.permission:giftcard.manage', 'rate.limit']);
            Route::post('disable', [AdminPlatformController::class, 'modelDisable'])->defaults('resource', 'giftcards')->middleware(['check.permission:giftcard.manage', 'rate.limit']);
        });
    });
});
