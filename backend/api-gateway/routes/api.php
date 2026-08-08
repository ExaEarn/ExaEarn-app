<?php

declare(strict_types=1);

use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\AgriController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\ExaPointController;
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
use App\Http\Controllers\WalletController;
use App\Http\Controllers\WebhookController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\PortfolioController;
use App\Http\Controllers\WithdrawalController;
use App\Http\Controllers\WithdrawalCenterController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\LedgerController;
use App\Http\Controllers\FiatWithdrawalController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\ProfileIdentityController;
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
use App\Http\Controllers\API\AITradingAssistantController;
use App\Http\Controllers\API\ExaAiController;
use App\Http\Controllers\API\ExaSkillsController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BlockchainEventController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;

Route::get('v1/market/klines', [TradeController::class, 'klines']);
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
});

Route::prefix('admin')->group(function (): void {
    Route::post('login', [AdminAuthController::class, 'login']);

    Route::middleware(['auth:sanctum', 'admin.security', 'admin.audit'])->group(function (): void {
        Route::post('logout', [AdminAuthController::class, 'logout']);
        Route::get('me', [AdminAuthController::class, 'me']);

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
        Route::post('exaskills/challenges/{challenge}/payout-winner', [ExaSkillsAdminController::class, 'payoutChallengeWinner'])->middleware('rate.limit');
        Route::get('crowdfunding', fn () => response()->json(['data' => [], 'message' => 'Crowdfunding module data']));
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
    });
    Route::prefix('exaskills')->group(function (): void {
        Route::get('home', [ExaSkillsController::class, 'home']);
        Route::get('categories', [ExaSkillsController::class, 'categories']);
        Route::get('courses', [ExaSkillsController::class, 'courses']);
        Route::get('courses/{course}', [ExaSkillsController::class, 'course']);
        Route::post('courses/{course}/enroll', [ExaSkillsController::class, 'enroll'])->middleware('rate.limit');
        Route::post('courses/{course}/purchase', [ExaSkillsController::class, 'purchaseCourse'])->middleware('rate.limit');
        Route::get('dashboard', [ExaSkillsController::class, 'dashboard']);
        Route::post('instructors/apply', [ExaSkillsController::class, 'instructorApply'])->middleware('rate.limit');
        Route::get('challenges', [ExaSkillsController::class, 'challenges']);
        Route::post('challenges/{challenge}/submissions', [ExaSkillsController::class, 'submitChallenge'])->middleware('rate.limit');
        Route::post('challenges/{challenge}/fund', [ExaSkillsController::class, 'fundChallenge'])->middleware('rate.limit');
        Route::get('opportunities', [ExaSkillsController::class, 'opportunities']);
        Route::post('opportunities/{opportunity}/applications', [ExaSkillsController::class, 'applyOpportunity'])->middleware('rate.limit');
    });
    Route::prefix('wallet')->group(function (): void {
        Route::get('balances', [WalletController::class, 'balances']);
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
        Route::post('quote', [SwapController::class, 'quote']);
        Route::post('execute', [SwapController::class, 'execute'])->middleware('rate.limit');
        Route::get('{swapId}', [SwapController::class, 'show']);
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

    Route::prefix('staking')->group(function (): void {
        Route::get('pools', [StakingController::class, 'pools']);
        Route::get('mine', [StakingController::class, 'myStakes']);
        Route::post('pools', [StakingController::class, 'createPool']);
        Route::post('stake', [StakingController::class, 'stake']);
        Route::post('{stakeId}/claim', [StakingController::class, 'claim']);
        Route::post('{stakeId}/compound', [StakingController::class, 'compound']);
        Route::post('{stakeId}/unstake', [StakingController::class, 'unstake']);
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

    Route::prefix('games/flight')->group(function (): void {
        Route::get('my-bets', [FlightGameController::class, 'myBets']);
        Route::post('bets', [FlightGameController::class, 'placeBet'])->middleware('rate.limit');
        Route::post('bets/{betUuid}/cashout', [FlightGameController::class, 'cashOut'])->middleware('rate.limit');
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
        Route::post('mint', [NftController::class, 'mint']);
        Route::post('assets/{nftId}/upgrade', [NftController::class, 'upgrade']);
        Route::post('assets/{nftId}/subscriptions', [NftController::class, 'subscribe']);
        Route::post('assets/{nftId}/listings', [NftController::class, 'createListing']);
        Route::post('listings/{listingId}/buy', [NftController::class, 'buyListing']);
        Route::post('assets/{nftId}/auctions', [NftController::class, 'createAuction']);
        Route::post('auctions/{auctionId}/bids', [NftController::class, 'bid']);
        Route::post('auctions/{auctionId}/finalize', [NftController::class, 'finalizeAuction']);
    });

    Route::prefix('notifications')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index']);
        Route::get('unread', [NotificationController::class, 'unread']);
        Route::get('stats', [NotificationController::class, 'stats']);
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
    });
    Route::prefix('admin/exaai')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('overview', [ExaAiAdminController::class, 'overview']);
        Route::get('plans', [ExaAiAdminController::class, 'plans']);
        Route::get('strategies', [ExaAiAdminController::class, 'strategies']);
        Route::get('sessions', [ExaAiAdminController::class, 'sessions']);
        Route::get('subscriptions', [ExaAiAdminController::class, 'subscriptions']);
        Route::get('trades', [ExaAiAdminController::class, 'trades']);
        Route::get('audit-logs', [ExaAiAdminController::class, 'auditLogs']);
    });
    Route::prefix('admin/giftcard')->middleware(['admin.security', 'admin.audit'])->group(function (): void {
        Route::get('submissions', [GiftCardAdminController::class, 'submissions']);
        Route::get('submissions/{id}', [GiftCardAdminController::class, 'submissionDetails']);
        Route::post('submissions/{id}/approve', [GiftCardAdminController::class, 'approve']);
        Route::post('submissions/{id}/reject', [GiftCardAdminController::class, 'reject']);
        Route::get('stats', [GiftCardAdminController::class, 'stats']);
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
        });

        Route::prefix('agri/projects')->group(function (): void {
            Route::get('/', [AdminPlatformController::class, 'modelIndex'])->defaults('resource', 'agri-projects')->middleware('check.permission:agri.manage');
            Route::post('/', [AdminPlatformController::class, 'modelStore'])->defaults('resource', 'agri-projects')->middleware(['check.permission:agri.manage', 'rate.limit']);
            Route::put('/', [AdminPlatformController::class, 'modelUpdate'])->defaults('resource', 'agri-projects')->middleware(['check.permission:agri.manage', 'rate.limit']);
            Route::post('close', [AdminPlatformController::class, 'modelDisable'])->defaults('resource', 'agri-projects')->middleware(['check.permission:agri.manage', 'rate.limit']);
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
