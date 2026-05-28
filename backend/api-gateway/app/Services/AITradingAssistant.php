<?php

namespace App\Services;

use App\Models\User;
use App\Models\AIAssistantConversation;
use App\Models\AIAssistantMessage;
use App\Models\TradingSignal;
use App\Models\TradingAuditLog;
use Illuminate\Support\Collection;

class AITradingAssistant
{
    public function __construct(
        private TradingSignalService $signalService,
        private EntryExitService $entryExitService,
        private RiskAdvisorService $riskAdvisor,
        private UserProfileAI $userProfileAI,
    ) {
    }

    /**
     * Start or get conversation
     */
    public function getOrCreateConversation(User $user, ?string $title = null, ?string $market_condition = null): AIAssistantConversation
    {
        // Get active conversation or create new one
        $conversation = $user->aiConversations()
            ->where('is_active', true)
            ->first();

        if ($conversation) {
            if ($market_condition) {
                $conversation->update(['market_condition' => $market_condition]);
            }
            return $conversation;
        }

        return $user->aiConversations()->create([
            'title' => $title ?? 'Trading Session ' . now()->format('Y-m-d H:i'),
            'context' => $this->generateConversationContext($user),
            'market_condition' => $market_condition ?? 'neutral',
            'is_active' => true,
        ]);
    }

    /**
     * Process user message and generate AI response
     */
    public function chat(User $user, string $userMessage, ?string $conversationId = null): AIAssistantMessage
    {
        // Get or create conversation
        $conversation = $conversationId 
            ? AIAssistantConversation::find($conversationId)
            : $this->getOrCreateConversation($user);

        // Store user message
        $userMsg = $conversation->messages()->create([
            'user_id' => $user->id,
            'role' => 'user',
            'message' => $userMessage,
        ]);

        // Generate AI response
        $aiResponse = $this->generateResponse($user, $userMessage, $conversation);

        // Store AI message
        $aiMsg = $conversation->messages()->create([
            'user_id' => $user->id,
            'role' => 'assistant',
            'message' => $aiResponse['message'],
            'suggestions' => $aiResponse['suggestions'] ?? [],
            'context_data' => $aiResponse['context'] ?? [],
        ]);

        // Log for audit trail
        TradingAuditLog::create([
            'user_id' => $user->id,
            'action_type' => 'ai_chat',
            'symbol' => $aiResponse['symbol'] ?? null,
            'details' => [
                'conversation_id' => $conversation->id,
                'user_input' => $userMessage,
            ],
            'ai_suggestion' => $aiResponse['ai_suggestion'] ?? [],
        ]);

        return $aiMsg;
    }

    /**
     * Generate AI response based on user input
     */
    private function generateResponse(User $user, string $input, AIAssistantConversation $conversation): array
    {
        $profile = $user->tradingProfile;
        $context = [
            'profile' => $profile ? [
                'skill_level' => $profile->skill_level,
                'risk_tolerance' => $profile->risk_tolerance,
                'account_balance' => $profile->account_balance,
            ] : null,
            'conversation_history' => $this->getConversationHistory($conversation),
        ];

        // Detect intent from user input
        $intent = $this->detectIntent($input);

        $response = match ($intent) {
            'signal_request' => $this->respondToSignalRequest($user, $input),
            'entry_exit' => $this->respondToEntryExitRequest($user, $input),
            'risk_check' => $this->respondToRiskCheck($user, $input),
            'position_advice' => $this->respondToPositionAdvice($user, $input),
            'strategy_question' => $this->respondToStrategyQuestion($user, $profile),
            'education' => $this->respondToEducation($input, $profile?->skill_level ?? 'beginner'),
            default => $this->respondToGeneral($user, $input),
        };

        return array_merge($response, ['context' => $context]);
    }

    /**
     * Detect user intent from message
     */
    private function detectIntent(string $input): string
    {
        $input = strtolower($input);

        if (preg_match('/signal|buy|sell|should i/i', $input)) {
            return 'signal_request';
        }
        if (preg_match('/entry|exit|stop|target|take profit/i', $input)) {
            return 'entry_exit';
        }
        if (preg_match('/risk|leverage|exposure|portfolio|safe/i', $input)) {
            return 'risk_check';
        }
        if (preg_match('/position|trade|open|close/i', $input)) {
            return 'position_advice';
        }
        if (preg_match('/strategy|scalp|grid|trend|how to/i', $input)) {
            return 'strategy_question';
        }
        if (preg_match('/learn|understand|what is|explain|education/i', $input)) {
            return 'education';
        }

        return 'general';
    }

    /**
     * Respond to signal/trading opportunity request
     */
    private function respondToSignalRequest(User $user, string $input): array
    {
        // Extract symbol if mentioned
        $symbol = $this->extractSymbol($input) ?? 'BTCUSD';

        $signal = $this->signalService->getSignalBySymbol($user, $symbol);

        if (!$signal || !$signal->is_active) {
            return [
                'message' => "I don't have an active signal for $symbol right now. Would you like me to generate a new analysis?",
                'suggestions' => [
                    ['type' => 'action', 'text' => 'Generate new signal', 'data' => ['symbol' => $symbol]],
                    ['type' => 'action', 'text' => 'View all signals', 'data' => []],
                ],
                'ai_suggestion' => ['type' => 'no_signal', 'symbol' => $symbol],
            ];
        }

        $entry = $this->entryExitService->suggestEntryExit($user, $signal);

        $message = "📊 **{$signal->signal_type} Signal for {$symbol}**\n";
        $message .= "Confidence: {$signal->confidence}%\n";
        $message .= "Market: {$signal->market_condition}\n\n";
        $message .= "Suggested Entry: \${$entry['entry_price']}\n";
        $message .= "Stop Loss: \${$entry['stop_loss']}\n";
        $message .= "Take Profit: \${$entry['take_profit']}\n";
        $message .= "Risk/Reward: 1:{$entry['risk_reward_ratio']}\n\n";
        $message .= $signal->ai_reasoning;

        return [
            'message' => $message,
            'symbol' => $symbol,
            'suggestions' => [
                ['type' => 'trade', 'text' => 'Place Trade', 'data' => $entry],
                ['type' => 'analyze', 'text' => 'Deep Dive', 'data' => ['signal_id' => $signal->id]],
            ],
            'ai_suggestion' => [
                'type' => 'signal',
                'signal_id' => $signal->id,
                'confidence' => $signal->confidence,
            ],
        ];
    }

    /**
     * Respond to entry/exit optimization request
     */
    private function respondToEntryExitRequest(User $user, string $input): array
    {
        $symbol = $this->extractSymbol($input) ?? 'BTCUSD';
        $signal = $this->signalService->getSignalBySymbol($user, $symbol);

        if (!$signal) {
            return [
                'message' => "I need a signal analysis first. Let me create one for $symbol.",
                'suggestions' => [],
                'ai_suggestion' => ['type' => 'generate_signal', 'symbol' => $symbol],
            ];
        }

        $entry = $this->entryExitService->suggestEntryExit($user, $signal);

        $message = "💡 **Entry & Exit Strategy for {$symbol}**\n\n";
        $message .= "**Entry:** \${$entry['entry_price']}\n";
        $message .= "Method: {$entry['entry_strategy']['type']}\n";
        $message .= "Description: {$entry['entry_strategy']['description']}\n\n";
        $message .= "**Exit Plan:**\n";
        $message .= "Type: {$entry['exit_strategy']['type']}\n";
        $message .= "Levels: {$entry['exit_strategy']['take_profit_levels']}\n";
        $message .= "Stop Loss: {$entry['exit_strategy']['stop_loss_percent']}%\n\n";
        $message .= "**Position Sizing:**\n";
        $message .= "Size: {$entry['position_size']} units\n";
        $message .= "Leverage: {$entry['leverage']}x\n";
        $message .= "Risk Amount: \${$entry['risk_per_trade']}";

        $targets = $this->entryExitService->getScaledExitTargets($signal, $entry['exit_strategy']['take_profit_levels']);

        return [
            'message' => $message,
            'symbol' => $symbol,
            'suggestions' => [
                ['type' => 'place_trade', 'text' => 'Execute Trade', 'data' => $entry],
                ['type' => 'adjust', 'text' => 'Adjust Levels', 'data' => ['symbol' => $symbol]],
            ],
            'ai_suggestion' => [
                'type' => 'entry_exit',
                'entry' => $entry['entry_price'],
                'stop_loss' => $entry['stop_loss'],
                'take_profit' => $entry['take_profit'],
                'targets' => $targets,
            ],
        ];
    }

    /**
     * Respond to risk assessment request
     */
    private function respondToRiskCheck(User $user, string $input): array
    {
        $riskAnalysis = $this->riskAdvisor->analyzeRiskExposure($user);

        $message = "⚠️ **Risk Assessment**\n\n";
        $message .= "Overall Risk Score: {$riskAnalysis['risk_score']}/100\n";
        $message .= "Status: " . ($riskAnalysis['is_safe'] ? '✅ Safe' : '⚠️ High Risk') . "\n\n";

        if (!empty($riskAnalysis['warnings'])) {
            $message .= "**Active Warnings:**\n";
            foreach ($riskAnalysis['warnings'] as $warning) {
                $severity = strtoupper($warning['severity'] ?? 'warning');
                $message .= "- [{$severity}] {$warning['message']}\n";
            }
        } else {
            $message .= "Your portfolio looks balanced. 👍\n";
        }

        $recommendations = $this->riskAdvisor->getRiskRecommendations($user);

        return [
            'message' => $message,
            'suggestions' => $recommendations->map(fn($rec) => [
                'type' => 'recommendation',
                'text' => $rec['title'],
                'data' => $rec,
            ])->toArray(),
            'ai_suggestion' => [
                'type' => 'risk_check',
                'risk_score' => $riskAnalysis['risk_score'],
                'is_safe' => $riskAnalysis['is_safe'],
                'warnings' => $riskAnalysis['warnings'],
            ],
        ];
    }

    /**
     * Respond to position management request
     */
    private function respondToPositionAdvice(User $user, string $input): array
    {
        return [
            'message' => "Position management: Would you like to review your current positions, or get advice on a specific trade?",
            'suggestions' => [
                ['type' => 'action', 'text' => 'View Positions', 'data' => []],
                ['type' => 'action', 'text' => 'Close Position', 'data' => []],
                ['type' => 'action', 'text' => 'Adjust Stop Loss', 'data' => []],
            ],
            'ai_suggestion' => ['type' => 'position_request'],
        ];
    }

    /**
     * Respond to strategy questions
     */
    private function respondToStrategyQuestion(User $user, ?object $profile): array
    {
        $skillLevel = $profile?->skill_level ?? 'beginner';

        $strategies = [
            'beginner' => [
                'Spot Trading' => 'Buy and hold without leverage. Simplest strategy.',
                'Dollar-Cost Averaging' => 'Invest fixed amount regularly. Reduces timing risk.',
            ],
            'intermediate' => [
                'Trend Following' => 'Trade in direction of trend. Follow moving averages.',
                'Support/Resistance' => 'Buy at support, sell at resistance.',
                'Grid Trading' => 'Place multiple orders at different levels.',
            ],
            'advanced' => [
                'Scalping' => 'Quick profits from small price moves.',
                'Arbitrage' => 'Exploit price differences across exchanges.',
                'Options Strategies' => 'Use derivatives for income or hedging.',
            ],
        ];

        $message = "📚 **Recommended Strategies for {$skillLevel} Traders:**\n\n";

        foreach ($strategies[$skillLevel] as $name => $description) {
            $message .= "**{$name}**: {$description}\n";
        }

        return [
            'message' => $message,
            'suggestions' => [],
            'ai_suggestion' => ['type' => 'strategy_education'],
        ];
    }

    /**
     * Respond to educational questions
     */
    private function respondToEducation(string $input, string $skillLevel): array
    {
        $message = match ($skillLevel) {
            'beginner' => "📖 **For beginners:**\n\n"
                . "1. **Understand the Market**: Learn about supply/demand, trends\n"
                . "2. **Risk Management**: Never risk more than 1% per trade\n"
                . "3. **Use Stop Losses**: Always protect your capital\n"
                . "4. **Start Small**: Build experience before increasing size",
            'intermediate' => "📖 **For intermediate traders:**\n\n"
                . "1. **Technical Analysis**: Master candlesticks, support/resistance\n"
                . "2. **Position Sizing**: Use risk per trade to size positions\n"
                . "3. **Money Management**: Track your win rate and PnL\n"
                . "4. **Strategy Testing**: Backtest before deploying real capital",
            'advanced' => "📖 **For advanced traders:**\n\n"
                . "1. **Correlation Analysis**: Understand asset class relationships\n"
                . "2. **Automated Trading**: Use algorithms for consistent execution\n"
                . "3. **Portfolio Optimization**: Maximize Sharpe ratio\n"
                . "4. **Risk Parity**: Balance portfolio by volatility, not capital",
            default => "📖 **General Trading Education:**\n\nAsk me about specific topics like risk management, technical analysis, or strategy selection.",
        };

        return [
            'message' => $message,
            'suggestions' => [],
            'ai_suggestion' => ['type' => 'education'],
        ];
    }

    /**
     * Respond to general questions
     */
    private function respondToGeneral(User $user, string $input): array
    {
        return [
            'message' => "I'm your AI trading assistant. I can help you with:\n"
                . "• 📊 Trading signals and analysis\n"
                . "• 💰 Entry/exit optimization\n"
                . "• ⚠️ Risk assessment\n"
                . "• 📈 Strategy selection\n"
                . "• 📚 Trading education\n\n"
                . "What would you like help with?",
            'suggestions' => [
                ['type' => 'action', 'text' => 'Get Trading Signal', 'data' => []],
                ['type' => 'action', 'text' => 'Check Risk Level', 'data' => []],
                ['type' => 'action', 'text' => 'Learn Strategies', 'data' => []],
            ],
            'ai_suggestion' => ['type' => 'general_help'],
        ];
    }

    /**
     * Extract symbol from user input
     */
    private function extractSymbol(string $input): ?string
    {
        $symbols = ['BTCUSD', 'ETHUSD', 'XRPUSD', 'LTCUSD', 'ADAUSD'];

        foreach ($symbols as $symbol) {
            if (stripos($input, $symbol) !== false || stripos($input, substr($symbol, 0, 3)) !== false) {
                return $symbol;
            }
        }

        return null;
    }

    /**
     * Get conversation history for context
     */
    private function getConversationHistory(AIAssistantConversation $conversation, int $limit = 5): array
    {
        return $conversation->messages()
            ->latest()
            ->limit($limit)
            ->get()
            ->reverse()
            ->map(fn($m) => [
                'role' => $m->role,
                'message' => substr($m->message, 0, 200),
            ])
            ->toArray();
    }

    /**
     * Generate conversation context
     */
    private function generateConversationContext(User $user): array
    {
        return [
            'user_id' => $user->id,
            'created_at' => now(),
            'profile' => $user->tradingProfile ? [
                'skill_level' => $user->tradingProfile->skill_level,
                'risk_tolerance' => $user->tradingProfile->risk_tolerance,
            ] : null,
        ];
    }

    /**
     * End conversation
     */
    public function endConversation(AIAssistantConversation $conversation): void
    {
        $conversation->update(['is_active' => false]);
    }

    /**
     * Get conversation list for user
     */
    public function getUserConversations(User $user, int $limit = 10): Collection
    {
        return $user->aiConversations()
            ->orderByDesc('created_at')
            ->limit($limit)
            ->get();
    }
}
