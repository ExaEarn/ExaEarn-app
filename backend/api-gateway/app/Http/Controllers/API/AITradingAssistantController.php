<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Http\Requests\AITrading\ChatRequest;
use App\Http\Requests\AITrading\CreateStrategyRequest;
use App\Http\Requests\AITrading\UpdateProfileRequest;
use App\Http\Requests\AITrading\GenerateSignalRequest;
use App\Models\AutoTradingStrategy;
use App\Models\TradingSignal;
use App\Models\UserTradingProfile;
use App\Services\AITradingAssistant;
use App\Services\AutoTradingService;
use App\Services\EntryExitService;
use App\Services\NotificationService;
use App\Services\RiskAdvisorService;
use App\Services\TradingSignalService;
use App\Services\UserProfileAI;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AITradingAssistantController extends Controller
{
    public function __construct(
        private AITradingAssistant $aiAssistant,
        private TradingSignalService $signalService,
        private EntryExitService $entryExitService,
        private RiskAdvisorService $riskAdvisor,
        private UserProfileAI $userProfileAI,
        private AutoTradingService $autoTradingService,
        private NotificationService $notificationService,
    ) {
    }

    /**
     * Get user trading profile
     * GET /api/ai/profile
     */
    public function getProfile(Request $request)
    {
        $user = $request->user();
        $profile = $user->tradingProfile;

        if (!$profile) {
            $profile = $this->userProfileAI->initializeProfile($user);
        }

        return response()->json([
            'success' => true,
            'profile' => $profile,
            'trade_modes' => [
                ['key' => 'manual', 'label' => 'Trade Without AI'],
                ['key' => 'assist', 'label' => 'AI Suggestions Only'],
                ['key' => 'auto', 'label' => 'Allow AI Auto-Trading'],
            ],
            'recommendations' => $this->userProfileAI->getSkillProgression($user),
        ]);
    }

    /**
     * Initialize trading profile for new user
     * POST /api/ai/profile/init
     */
    public function initializeProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();

        $profile = $this->userProfileAI->initializeProfile($user, $request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Profile initialized successfully',
            'profile' => $profile,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update trading profile
     * PATCH /api/ai/profile
     */
    public function updateProfile(UpdateProfileRequest $request)
    {
        $user = $request->user();
        $profile = $user->tradingProfile ?? $this->userProfileAI->initializeProfile($user);

        $data = $request->validated();
        if (isset($data['ai_trade_mode'])) {
            [$suggestions, $auto] = $this->resolveModeFlags($data['ai_trade_mode']);
            $data['enable_ai_suggestions'] = $suggestions;
            $data['enable_auto_trading'] = $auto;
        }

        if (isset($data['enable_auto_trading']) && $data['enable_auto_trading'] === true && !isset($data['ai_trade_mode'])) {
            $data['ai_trade_mode'] = 'auto';
            $data['enable_ai_suggestions'] = true;
        }

        if (isset($data['enable_ai_suggestions']) && $data['enable_ai_suggestions'] === false && !isset($data['ai_trade_mode'])) {
            $data['ai_trade_mode'] = 'manual';
            $data['enable_auto_trading'] = false;
        }

        $profile->update($data);

        // Safety: when not in auto mode, disable active strategies immediately.
        if (($profile->ai_trade_mode ?? 'assist') !== 'auto') {
            $user->autoTradingStrategies()->where('is_active', true)->update(['is_active' => false]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully',
            'profile' => $profile,
        ]);
    }

    /**
     * Get active trading signals
     * GET /api/ai/signals
     */
    public function getSignals(Request $request)
    {
        $user = $request->user();
        $limit = $request->get('limit', 20);

        $signals = $this->signalService->getUserSignals($user, $limit);

        return response()->json([
            'success' => true,
            'count' => $signals->count(),
            'signals' => $signals->map(fn($s) => [
                'id' => $s->id,
                'symbol' => $s->symbol,
                'type' => $s->signal_type,
                'confidence' => $s->confidence,
                'entry' => $s->suggested_entry,
                'stop_loss' => $s->suggested_stop_loss,
                'take_profit' => $s->suggested_take_profit,
                'created_at' => $s->created_at,
            ]),
        ]);
    }

    /**
     * Get signal details with entry/exit suggestions
     * GET /api/ai/signals/{id}
     */
    public function getSignal(TradingSignal $signal, Request $request)
    {
        $this->authorize('view', $signal);

        $user = $request->user();
        $entry = $this->entryExitService->suggestEntryExit($user, $signal);

        return response()->json([
            'success' => true,
            'signal' => [
                'id' => $signal->id,
                'symbol' => $signal->symbol,
                'type' => $signal->signal_type,
                'confidence' => $signal->confidence,
                'reasoning' => $signal->ai_reasoning,
                'market_condition' => $signal->market_condition,
                'volatility' => $signal->volatility_level,
                'trend_strength' => $signal->trend_strength,
                'indicators' => $signal->technical_indicators,
            ],
            'entry_exit' => $entry,
            'scaled_targets' => $this->entryExitService->getScaledExitTargets($signal, 3),
        ]);
    }

    /**
     * Generate new trading signal
     * POST /api/ai/signals/generate
     */
    public function generateSignal(GenerateSignalRequest $request)
    {
        $user = $request->user();
        $symbol = $request->get('symbol', 'BTCUSD');

        // Mock market data - in production, fetch from price feed
        $marketData = [
            'current_price' => 42000,
            'high_24h' => 43000,
            'low_24h' => 41000,
            'volume_24h' => 1000000,
            'avg_volume' => 950000,
            'prices' => array_fill(0, 30, 42000),  // Mock price history
        ];

        $signal = $this->signalService->generateSignal($user, $symbol, $marketData);

        return response()->json([
            'success' => true,
            'message' => 'Signal generated successfully',
            'signal' => $signal,
        ], Response::HTTP_CREATED);
    }

    /**
     * Get risk assessment
     * GET /api/ai/risk-assessment
     */
    public function getRiskAssessment(Request $request)
    {
        $user = $request->user();
        
        // Mock open positions - in production, fetch from futures positions
        $openPositions = [];

        $riskAnalysis = $this->riskAdvisor->analyzeRiskExposure($user, $openPositions);

        return response()->json([
            'success' => true,
            'risk_score' => $riskAnalysis['risk_score'],
            'is_safe' => $riskAnalysis['is_safe'],
            'warnings' => $riskAnalysis['warnings'],
            'recommendations' => $this->riskAdvisor->getRiskRecommendations($user),
        ]);
    }

    /**
     * Validate a proposed trade
     * POST /api/ai/validate-trade
     */
    public function validateTrade(Request $request)
    {
        $user = $request->user();

        $validation = $this->riskAdvisor->validateProposedTrade($user, $request->all());

        return response()->json([
            'success' => $validation['valid'],
            'valid' => $validation['valid'],
            'errors' => $validation['errors'],
        ]);
    }

    /**
     * Chat with AI assistant
     * POST /api/ai/assistant/chat
     */
    public function chat(ChatRequest $request)
    {
        $user = $request->user();

        $message = $this->aiAssistant->chat(
            $user,
            $request->get('message'),
            $request->get('conversation_id')
        );

        $this->notificationService->create(
            $user,
            'ai_assistant',
            'ExaAI response ready',
            str($message->message)->limit(120)->toString(),
            channels: ['in_app'],
            data: [
                'source' => 'main_ai',
                'action_page' => 'aiAssistant',
                'conversation_id' => $message->conversation_id,
                'message_id' => $message->id,
            ]
        );

        return response()->json([
            'success' => true,
            'message' => $message->load('conversation'),
        ], Response::HTTP_CREATED);
    }

    /**
     * Get conversation history
     * GET /api/ai/assistant/conversations/{id}
     */
    public function getConversation($conversationId, Request $request)
    {
        $user = $request->user();

        $conversation = $user->aiConversations()
            ->findOrFail($conversationId);

        return response()->json([
            'success' => true,
            'conversation' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'created_at' => $conversation->created_at,
                'messages' => $conversation->messages()
                    ->latest()
                    ->limit(50)
                    ->get()
                    ->reverse()
                    ->values(),
            ],
        ]);
    }

    /**
     * List user conversations
     * GET /api/ai/assistant/conversations
     */
    public function listConversations(Request $request)
    {
        $user = $request->user();

        $conversations = $this->aiAssistant->getUserConversations($user);

        return response()->json([
            'success' => true,
            'count' => $conversations->count(),
            'conversations' => $conversations->map(fn($c) => [
                'id' => $c->id,
                'title' => $c->title,
                'message_count' => $c->messages()->count(),
                'updated_at' => $c->updated_at,
            ]),
        ]);
    }

    /**
     * Get personalized recommendations
     * GET /api/ai/recommendations
     */
    public function getRecommendations(Request $request)
    {
        $user = $request->user();

        $profile = $user->tradingProfile;

        if (!$profile) {
            return response()->json([
                'success' => true,
                'recommendations' => [],
                'message' => 'Please set up your profile first',
            ]);
        }

        $suggestions = $this->userProfileAI->getPersonalizedSuggestions($user);
        $feedback = $this->userProfileAI->getLearningFeedback($user);

        return response()->json([
            'success' => true,
            'suggestions' => $suggestions,
            'feedback' => $feedback,
            'progression' => $this->userProfileAI->getSkillProgression($user),
        ]);
    }

    /**
     * List auto-trading strategies
     * GET /api/ai/strategies
     */
    public function listStrategies(Request $request)
    {
        $user = $request->user();
        $activeOnly = $request->get('active_only', true);

        $strategies = $this->autoTradingService->getUserStrategies($user, $activeOnly);

        return response()->json([
            'success' => true,
            'count' => $strategies->count(),
            'strategies' => $strategies->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name,
                'type' => $s->type,
                'symbol' => $s->symbol,
                'is_active' => $s->is_active,
                'metrics' => $this->autoTradingService->getStrategyMetrics($s),
            ]),
        ]);
    }

    /**
     * Create auto-trading strategy
     * POST /api/ai/strategies
     */
    public function createStrategy(CreateStrategyRequest $request)
    {
        $user = $request->user();

        $strategy = $user->autoTradingStrategies()->create(
            array_merge($request->validated(), ['is_active' => false])
        );

        return response()->json([
            'success' => true,
            'message' => 'Strategy created successfully',
            'strategy' => $strategy,
        ], Response::HTTP_CREATED);
    }

    /**
     * Update auto-trading strategy
     * PATCH /api/ai/strategies/{id}
     */
    public function updateStrategy(AutoTradingStrategy $strategy, Request $request)
    {
        $this->authorize('update', $strategy);

        $strategy->update($request->only([
            'name', 'config', 'max_drawdown_percent', 'daily_loss_limit'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Strategy updated successfully',
            'strategy' => $strategy,
        ]);
    }

    /**
     * Activate strategy
     * POST /api/ai/strategies/{id}/activate
     */
    public function activateStrategy(AutoTradingStrategy $strategy, Request $request)
    {
        $this->authorize('update', $strategy);
        $profile = $request->user()->tradingProfile;
        $mode = (string) ($profile?->ai_trade_mode ?? 'assist');

        if ($mode !== 'auto') {
            return response()->json([
                'success' => false,
                'message' => 'Enable auto mode before activating strategies.',
                'current_mode' => $mode,
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $strategy->update(['is_active' => true]);

        return response()->json([
            'success' => true,
            'message' => 'Strategy activated',
            'strategy' => $strategy,
        ]);
    }

    /**
     * Deactivate strategy
     * POST /api/ai/strategies/{id}/deactivate
     */
    public function deactivateStrategy(AutoTradingStrategy $strategy, Request $request)
    {
        $this->authorize('update', $strategy);

        $strategy->update(['is_active' => false]);

        return response()->json([
            'success' => true,
            'message' => 'Strategy deactivated',
            'strategy' => $strategy,
        ]);
    }

    /**
     * Get strategy performance
     * GET /api/ai/strategies/{id}/metrics
     */
    public function getStrategyMetrics(AutoTradingStrategy $strategy, Request $request)
    {
        $this->authorize('view', $strategy);

        $metrics = $this->autoTradingService->getStrategyMetrics($strategy);

        return response()->json([
            'success' => true,
            'strategy_id' => $strategy->id,
            'metrics' => $metrics,
        ]);
    }

    /**
     * Delete strategy
     * DELETE /api/ai/strategies/{id}
     */
    public function deleteStrategy(AutoTradingStrategy $strategy, Request $request)
    {
        $this->authorize('delete', $strategy);

        $strategy->delete();

        return response()->json([
            'success' => true,
            'message' => 'Strategy deleted',
        ]);
    }

    private function resolveModeFlags(string $mode): array
    {
        return match ($mode) {
            'manual' => [false, false],
            'auto' => [true, true],
            default => [true, false], // assist
        };
    }
}
