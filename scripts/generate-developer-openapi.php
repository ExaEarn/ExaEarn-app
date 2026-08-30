<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;

$root = dirname(__DIR__);
require $root . '/backend/api-gateway/vendor/autoload.php';
$app = require $root . '/backend/api-gateway/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$requestSchemas = [
    'post api/developer/v1/spot/orders' => 'SpotOrderRequest',
    'post api/developer/v1/futures/orders' => 'FuturesOrderRequest',
    'post api/developer/v1/futures/orders/validate' => 'FuturesOrderRequest',
    'post api/developer/v1/futures/orders/conditional' => 'FuturesConditionalOrderRequest',
    'post api/developer/v1/futures/orders/batch-cancel' => 'BatchCancelRequest',
    'post api/developer/v1/futures/margin/type' => 'FuturesMarginTypeRequest',
    'post api/developer/v1/margin/accounts' => 'MarginAccountRequest',
    'post api/developer/v1/margin/transfer' => 'MarginTransferRequest',
    'post api/developer/v1/margin/borrow' => 'MarginBorrowRequest',
    'post api/developer/v1/margin/loans/{loanUuid}/repay' => 'MarginRepayRequest',
    'post api/developer/v1/margin/orders' => 'MarginOrderRequest',
    'post api/developer/v1/staking/positions' => 'StakingPositionRequest',
    'post api/developer/v1/staking/positions/{publicId}/unstake' => 'StakingUnstakeRequest',
    'post api/developer/v1/copy/follow' => 'CopyFollowRequest',
    'post api/developer/v1/exaai/allocations' => 'ExaAiAllocationRequest',
    'post api/developer/v1/realtime/session' => 'RealtimeSessionRequest',
    'post api/developer/v1/convert/quote' => 'ConvertQuoteRequest',
    'post api/developer/v1/convert/execute' => 'ConvertExecuteRequest',
    'post api/developer/v1/copy/terms/accept' => 'CopyTermsRequest',
    'patch api/developer/v1/copy/follow/{id}' => 'CopyFollowUpdateRequest',
    'delete api/developer/v1/copy/follow/{id}' => 'CopyStopRequest',
    'post api/developer/v1/exaai/terms/accept' => 'ExaAiTermsRequest',
    'post api/developer/v1/exaai/sessions' => 'ExaAiSessionRequest',
    'post api/developer/v1/exapay/merchants/{merchantId}/payment-intents' => 'ExaPayIntentRequest',
    'post api/developer/v1/exapay/merchants/{merchantId}/payment-links' => 'ExaPayLinkRequest',
    'post api/developer/v1/exapay/merchants/{merchantId}/refunds' => 'ExaPayRefundRequest',
    'patch api/developer/v1/staking/positions/{publicId}/auto-compound' => 'StakingAutoCompoundRequest',
    'post api/developer/v1/staking/terms/accept' => 'TermsAcceptanceRequest',
];

$bodylessWrites = [
    'delete api/developer/v1/futures/orders/{orderUuid}',
    'post api/developer/v1/exaai/sessions/{id}/pause',
    'post api/developer/v1/exaai/sessions/{id}/resume',
    'post api/developer/v1/exaai/sessions/{id}/stop',
    'post api/developer/v1/exapay/payment-intents/{payIntent}/capture',
    'post api/developer/v1/margin/orders/{marginOrderUuid}/cancel',
    'post api/developer/v1/staking/positions/{publicId}/claim-native-rewards',
    'post api/developer/v1/staking/positions/{publicId}/claim-exatoken-rewards',
];

$paths = [];
foreach (app('router')->getRoutes() as $route) {
    $uri = $route->uri();
    if (! str_starts_with($uri, 'api/developer/v1/')) {
        continue;
    }

    $middleware = $route->gatherMiddleware();
    $scope = null;
    foreach ($middleware as $item) {
        if (str_starts_with((string) $item, 'developer.api:')) {
            $scope = substr((string) $item, strlen('developer.api:'));
            break;
        }
    }
    $status = match (true) {
        str_contains($uri, '/futures/'), str_contains($uri, '/margin/'), str_contains($uri, '/copy/'), str_contains($uri, '/exaai/') => 'RESTRICTED',
        str_contains($uri, '/exapay/') => 'BETA',
        str_contains($uri, 'claim-exatoken') => 'RESTRICTED',
        str_contains($uri, 'auto-compound') => 'BETA',
        default => 'STABLE',
    };

    foreach (array_values(array_diff($route->methods(), ['HEAD'])) as $method) {
        $lowerMethod = strtolower($method);
        $path = '/' . $uri;
        $operationId = $lowerMethod . str_replace(' ', '', ucwords(preg_replace('/[{}\/_-]+/', ' ', substr($uri, strlen('api/developer/v1/')))));
        $parameters = [];
        preg_match_all('/\{([^}]+)\}/', $uri, $matches);
        foreach ($matches[1] ?? [] as $parameter) {
            $parameters[] = [
                'name' => $parameter,
                'in' => 'path',
                'required' => true,
                'schema' => ['type' => 'string'],
                'description' => 'Canonical resource identifier.',
            ];
        }
        if ($method === 'GET') {
            $parameters[] = [
                'name' => 'limit',
                'in' => 'query',
                'required' => false,
                'schema' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 1000],
                'description' => 'Bounded result limit where supported; unsupported query fields are ignored or rejected by the endpoint contract.',
            ];
        }

        $operation = [
            'operationId' => $operationId,
            'tags' => [match (true) {
                str_contains($uri, '/futures/') => 'Futures',
                str_contains($uri, '/margin/') => 'Margin',
                str_contains($uri, '/staking/') => 'Earn / Staking',
                str_contains($uri, '/copy/') => 'Copy Trading',
                str_contains($uri, '/exaai/') => 'ExaAI',
                str_contains($uri, '/convert/') => 'Convert',
                str_contains($uri, '/wallet/') => 'Wallet',
                str_contains($uri, '/spot/') => 'Spot',
                str_contains($uri, '/realtime/') => 'Realtime',
                default => 'Market Data',
            }],
            'summary' => ucwords(str_replace(['-', '_'], ' ', basename($uri))),
            'description' => 'ExaEarn Developer API contract backed by the canonical product service. Product status and scope are authoritative metadata.',
            'security' => $scope === null ? [] : [['ExaSignedRequest' => []]],
            'x-exaearn-status' => $status,
            'x-exaearn-scope' => $scope,
            'x-exaearn-rate-limit' => $method === 'GET' ? ($scope === null ? '240/minute' : '120/minute') : '60/minute',
            'parameters' => $parameters,
            'responses' => [
                (in_array($method, ['POST'], true) ? '201' : '200') => [
                    'description' => 'Successful ExaEarn response.',
                    'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessResponse']]],
                ],
                '400' => ['$ref' => '#/components/responses/ValidationError'],
                '401' => ['$ref' => '#/components/responses/AuthError'],
                '403' => ['$ref' => '#/components/responses/PermissionError'],
                '404' => ['$ref' => '#/components/responses/NotFoundError'],
                '409' => ['$ref' => '#/components/responses/ConflictError'],
                '422' => ['$ref' => '#/components/responses/BusinessRuleError'],
                '429' => ['$ref' => '#/components/responses/RateLimitError'],
                '500' => ['$ref' => '#/components/responses/InternalError'],
            ],
        ];

        $schema = $requestSchemas[$lowerMethod . ' ' . $uri] ?? null;
        $operationKey = $lowerMethod . ' ' . $uri;
        if (in_array($operationKey, $bodylessWrites, true)) {
            $operation['x-exaearn-request-body'] = 'NONE';
            $operation['x-exaearn-request-body-reason'] = 'The authoritative controller accepts no request fields for this state transition.';
        }
        if ((in_array($method, ['POST', 'PATCH', 'PUT', 'DELETE'], true)) && ! in_array($operationKey, $bodylessWrites, true)) {
            if ($schema === null) {
                throw new RuntimeException("Developer write route {$operationKey} has no explicit request schema or bodyless declaration.");
            }
            $operation['requestBody'] = [
                'required' => true,
                'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/' . $schema]]],
            ];
        }
        $paths[$path][$lowerMethod] = $operation;
    }
}

$productionAccessResponses = [
    '200' => ['description' => 'Production Access state and capability decisions.', 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/SuccessResponse']]]],
    '401' => ['$ref' => '#/components/responses/AuthError'],
    '403' => ['$ref' => '#/components/responses/PermissionError'],
    '409' => ['$ref' => '#/components/responses/ConflictError'],
    '422' => ['$ref' => '#/components/responses/BusinessRuleError'],
    '429' => ['$ref' => '#/components/responses/RateLimitError'],
];
$productionProjectParameter = [['name'=>'projectId','in'=>'path','required'=>true,'schema'=>['type'=>'integer'],'description'=>'Authorized Developer project ID.']];
$paths['/api/developer/projects/{projectId}/production-access'] = [
    'get' => ['operationId'=>'getProductionAccess','tags'=>['Production Access'],'summary'=>'Get Production Access status','description'=>'Returns safe request history and per-capability status. Internal compliance notes are never returned.','security'=>[['DeveloperSession'=>[]]],'x-exaearn-status'=>'BETA','x-exaearn-permission'=>'production_access.read','parameters'=>$productionProjectParameter,'responses'=>$productionAccessResponses],
    'post' => ['operationId'=>'requestProductionAccess','tags'=>['Production Access'],'summary'=>'Request Production Access','description'=>'Submits an idempotent, capability-level request. Approval does not create an API key.','security'=>[['DeveloperSession'=>[]]],'x-exaearn-status'=>'BETA','x-exaearn-permission'=>'production_access.request','parameters'=>$productionProjectParameter,'requestBody'=>['required'=>true,'content'=>['application/json'=>['schema'=>['$ref'=>'#/components/schemas/ProductionAccessRequest']]]],'responses'=>['201'=>$productionAccessResponses['200']]+array_diff_key($productionAccessResponses,['200'=>true])],
];
ksort($paths);

$decimal = ['type' => 'string', 'pattern' => '^-?[0-9]+(?:\\.[0-9]+)?$', 'example' => '100.00000000'];
$schemas = [
    'ProductionAccessRequest' => ['type'=>'object','additionalProperties'=>false,'required'=>['use_case','capabilities','idempotency_key'],'properties'=>['use_case'=>['type'=>'string','enum'=>['trading_application','trading_bot','portfolio_application','market_data_service','payment_integration','wallet_integration','institutional_integration','fintech_application','internal_company_integration','other']],'capabilities'=>['type'=>'array','minItems'=>1,'items'=>['type'=>'string']],'idempotency_key'=>['type'=>'string','maxLength'=>100],'expected_request_volume'=>['type'=>['string','null']],'expected_trading_volume'=>['type'=>['string','null']],'website'=>['type'=>['string','null'],'format'=>'uri'],'technical_contact'=>['type'=>['string','null'],'format'=>'email'],'business_context'=>['type'=>['string','null'],'maxLength'=>2000]],'example'=>['use_case'=>'trading_application','capabilities'=>['account.read','spot.read'],'idempotency_key'=>'prod-access-demo-001']],
    'DecimalString' => $decimal,
    'SuccessResponse' => ['type' => 'object', 'required' => ['success', 'data'], 'properties' => ['success' => ['type' => 'boolean'], 'data' => [], 'timestamp' => ['type' => 'integer']]],
    'DeveloperError' => ['type' => 'object', 'required' => ['success', 'error'], 'properties' => ['success' => ['type' => 'boolean', 'const' => false], 'error' => ['type' => 'object', 'required' => ['code', 'message'], 'properties' => ['code' => ['type' => 'string'], 'message' => ['type' => 'string'], 'request_id' => ['type' => ['string', 'null']], 'details' => ['type' => 'object', 'additionalProperties' => true]]], 'timestamp' => ['type' => 'integer']]],
    'SpotOrderRequest' => ['type' => 'object', 'required' => ['symbol', 'side', 'type', 'quantity'], 'properties' => ['symbol' => ['type' => 'string'], 'side' => ['type' => 'string', 'enum' => ['buy', 'sell']], 'type' => ['type' => 'string', 'enum' => ['market', 'limit', 'stop_loss', 'take_profit']], 'quantity' => $decimal, 'price' => $decimal, 'client_order_id' => ['type' => 'string', 'maxLength' => 80]]],
    'FuturesOrderRequest' => ['type' => 'object', 'required' => ['symbol', 'type', 'side', 'quantity', 'leverage'], 'properties' => ['symbol' => ['type' => 'string', 'maxLength' => 32], 'type' => ['type' => 'string', 'enum' => ['market', 'limit', 'stop-market', 'stop-limit', 'trailing-stop']], 'side' => ['type' => 'string', 'enum' => ['long', 'short']], 'quantity' => $decimal, 'price' => $decimal, 'stop_price' => $decimal, 'leverage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100], 'reduce_only' => ['type' => 'boolean'], 'post_only' => ['type' => 'boolean'], 'time_in_force' => ['type' => 'string', 'enum' => ['GTC', 'IOC', 'FOK']], 'client_order_id' => ['type' => 'string', 'maxLength' => 80]]],
    'FuturesConditionalOrderRequest' => ['type' => 'object', 'required' => ['symbol', 'type', 'trigger_order_type', 'trigger_price', 'quantity'], 'properties' => ['symbol' => ['type' => 'string'], 'type' => ['type' => 'string', 'enum' => ['stop_loss', 'take_profit']], 'trigger_order_type' => ['type' => 'string', 'enum' => ['market', 'limit']], 'trigger_price' => $decimal, 'execution_price' => $decimal, 'quantity' => $decimal]],
    'BatchCancelRequest' => ['type' => 'object', 'required' => ['order_uuids'], 'properties' => ['order_uuids' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 50, 'items' => ['type' => 'string', 'format' => 'uuid']]]],
    'FuturesMarginTypeRequest' => ['type' => 'object', 'required' => ['position_id', 'margin_type'], 'properties' => ['position_id' => ['type' => 'integer'], 'margin_type' => ['type' => 'string', 'enum' => ['cross', 'isolated']]]],
    'MarginAccountRequest' => ['type' => 'object', 'required' => ['mode'], 'properties' => ['mode' => ['type' => 'string', 'enum' => ['CROSS', 'ISOLATED']], 'market_symbol' => ['type' => 'string']]],
    'MarginTransferRequest' => ['type' => 'object', 'required' => ['account_uuid', 'direction', 'asset', 'amount'], 'properties' => ['account_uuid' => ['type' => 'string'], 'direction' => ['type' => 'string', 'enum' => ['IN', 'OUT']], 'asset' => ['type' => 'string'], 'amount' => $decimal, 'idempotency_key' => ['type' => 'string', 'maxLength' => 120]]],
    'MarginBorrowRequest' => ['type' => 'object', 'required' => ['account_uuid', 'asset', 'amount'], 'properties' => ['account_uuid' => ['type' => 'string'], 'asset' => ['type' => 'string'], 'amount' => $decimal, 'idempotency_key' => ['type' => 'string', 'maxLength' => 120]]],
    'MarginRepayRequest' => ['type' => 'object', 'required' => ['amount'], 'properties' => ['amount' => $decimal, 'idempotency_key' => ['type' => 'string', 'maxLength' => 120]]],
    'MarginOrderRequest' => ['type' => 'object', 'required' => ['account_uuid', 'client_order_id', 'pair', 'side', 'type', 'amount'], 'properties' => ['account_uuid' => ['type' => 'string'], 'client_order_id' => ['type' => 'string'], 'pair' => ['type' => 'string'], 'side' => ['type' => 'string', 'enum' => ['buy', 'sell']], 'type' => ['type' => 'string', 'enum' => ['limit', 'market']], 'amount' => $decimal, 'price' => $decimal, 'borrow_mode' => ['type' => 'string', 'enum' => ['NORMAL', 'AUTO_BORROW', 'AUTO_REPAY']]]],
    'StakingPositionRequest' => ['type' => 'object', 'required' => ['staking_product_id', 'amount', 'terms_version', 'idempotency_key'], 'properties' => ['staking_product_id' => ['type' => 'integer'], 'amount' => $decimal, 'auto_compound' => ['type' => 'boolean'], 'terms_version' => ['type' => 'string'], 'idempotency_key' => ['type' => 'string']]],
    'StakingUnstakeRequest' => ['type' => 'object', 'required' => ['idempotency_key'], 'properties' => ['amount' => $decimal, 'idempotency_key' => ['type' => 'string']]],
    'CopyFollowRequest' => ['type' => 'object', 'required' => ['trader_id', 'amount_allocated'], 'properties' => ['trader_id' => ['type' => 'integer'], 'amount_allocated' => $decimal, 'risk_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']]]],
    'ExaAiAllocationRequest' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Validated by existing ExaAI allocation governance; financial amounts are decimal strings.'],
    'RealtimeSessionRequest' => ['type' => 'object', 'required' => ['topics'], 'properties' => ['topics' => ['type' => 'array', 'minItems' => 1, 'maxItems' => 100, 'items' => ['type' => 'string']]]],
];

$string = fn (int $max, ?array $enum = null): array => array_filter(['type' => 'string', 'maxLength' => $max, 'enum' => $enum], fn ($value) => $value !== null);
$schemas['ConvertQuoteRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['from_currency', 'to_currency', 'amount'], 'properties' => [
    'from_currency' => $string(16), 'to_currency' => $string(16), 'amount' => $decimal,
], 'example' => ['from_currency' => 'USDT', 'to_currency' => 'BTC', 'amount' => '100.00000000']];
$schemas['ConvertExecuteRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['quote_id'], 'properties' => [
    'quote_id' => ['type' => 'string', 'format' => 'uuid'],
], 'example' => ['quote_id' => '018f4fd8-4f0a-7d42-9a50-23de9f6c30d2']];
$schemas['TermsAcceptanceRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['terms_version'], 'properties' => [
    'terms_version' => $string(32),
], 'example' => ['terms_version' => 'staking-v1']];
$schemas['StakingAutoCompoundRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['auto_compound'], 'properties' => [
    'auto_compound' => ['type' => 'boolean'],
], 'example' => ['auto_compound' => true]];
$schemas['CopyTermsRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['types'], 'properties' => [
    'types' => ['type' => 'array', 'minItems' => 1, 'uniqueItems' => true, 'items' => ['type' => 'string', 'enum' => ['copy_trading_terms', 'risk_disclosure', 'futures_copy_disclosure', 'profit_share_terms']]],
], 'example' => ['types' => ['copy_trading_terms', 'risk_disclosure']]];
$schemas['CopyFollowRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['trader_id', 'amount_allocated'], 'properties' => [
    'trader_id' => ['type' => 'integer', 'minimum' => 1], 'amount_allocated' => $decimal,
    'risk_level' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
    'product_scope' => ['type' => 'string', 'enum' => ['spot', 'futures', 'all']],
    'copy_mode' => ['type' => 'string', 'enum' => ['fixed_amount', 'proportional', 'fixed_ratio']],
    'fixed_amount_per_trade' => $decimal, 'fixed_ratio' => $decimal, 'max_amount_per_trade' => $decimal,
    'max_daily_loss' => $decimal, 'max_drawdown' => $decimal,
    'max_leverage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 125],
    'margin_preference' => ['type' => 'string', 'enum' => ['isolated', 'cross', 'follow_lead']],
    'allowed_symbols' => ['type' => 'array', 'items' => ['type' => 'string']],
    'country' => ['type' => 'string', 'minLength' => 2, 'maxLength' => 2],
], 'example' => ['trader_id' => 12, 'amount_allocated' => '250.00000000', 'risk_level' => 'medium', 'product_scope' => 'spot']];
$schemas['CopyFollowUpdateRequest'] = ['type' => 'object', 'additionalProperties' => false, 'minProperties' => 1, 'properties' => [
    'max_amount_per_trade' => $decimal, 'max_daily_loss' => $decimal, 'max_drawdown' => $decimal,
    'max_leverage' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 125],
    'allowed_symbols' => ['type' => 'array', 'items' => ['type' => 'string']],
    'status' => ['type' => 'string', 'enum' => ['active', 'paused']],
], 'example' => ['max_amount_per_trade' => '50.00000000', 'status' => 'active']];
$schemas['CopyStopRequest'] = ['type' => 'object', 'additionalProperties' => false, 'properties' => [
    'action' => ['type' => 'string', 'enum' => ['STOP_NEW_TRADES', 'STOP_AND_CLOSE_COPIED_POSITIONS', 'DETACH_POSITION']],
    'reason' => $string(500),
], 'example' => ['action' => 'STOP_NEW_TRADES', 'reason' => 'Sandbox lifecycle test']];
$schemas['ExaAiAllocationRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['asset', 'amount'], 'properties' => [
    'asset' => $string(20), 'amount' => $decimal,
], 'example' => ['asset' => 'USDT', 'amount' => '500.00000000']];
$schemas['ExaAiTermsRequest'] = ['type' => 'object', 'additionalProperties' => false, 'properties' => [
    'terms_version' => $string(40),
], 'example' => ['terms_version' => 'phase13-v1']];
$schemas['ExaAiSessionRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['allocation_id', 'strategy_id'], 'properties' => [
    'allocation_id' => ['type' => 'integer', 'minimum' => 1], 'strategy_id' => ['type' => 'integer', 'minimum' => 1],
    'mode' => ['type' => 'string', 'enum' => ['paper', 'shadow', 'live', 'demo']], 'live_authorization' => ['type' => 'boolean'],
    'duration' => ['type' => 'string', 'enum' => ['24h', '7d', '30d', '90d', 'manual']],
    'max_daily_loss' => $decimal, 'max_drawdown_percent' => $decimal,
    'max_open_positions' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 100],
    'eligible_markets' => ['type' => 'array', 'items' => $string(40)],
    'constraints' => ['type' => 'object', 'additionalProperties' => true, 'description' => 'Strategy-defined non-financial constraint map validated by ExaAI governance.'],
], 'example' => ['allocation_id' => 10, 'strategy_id' => 3, 'mode' => 'shadow', 'duration' => '7d', 'max_daily_loss' => '25.00000000']];
$schemas['ExaPayIntentRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['amount', 'currency', 'idempotency_key'], 'properties' => [
    'payer_user_id' => ['type' => 'integer', 'minimum' => 1], 'amount' => $decimal, 'currency' => $string(8),
    'description' => $string(200), 'merchant_reference' => $string(160), 'customer_reference' => $string(160),
    'environment' => ['type' => 'string', 'enum' => ['SANDBOX', 'PRODUCTION', 'sandbox', 'production']],
    'capture_mode' => ['type' => 'string', 'enum' => ['AUTOMATIC', 'MANUAL', 'automatic', 'manual']],
    'payment_method' => $string(40), 'idempotency_key' => $string(160),
    'expires_at' => ['type' => 'string', 'format' => 'date-time'], 'metadata' => ['type' => 'object', 'additionalProperties' => true],
], 'example' => ['amount' => '25.00000000', 'currency' => 'USDT', 'environment' => 'sandbox', 'capture_mode' => 'AUTOMATIC', 'idempotency_key' => 'sandbox-pay-001']];
$schemas['ExaPayLinkRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['title'], 'properties' => [
    'title' => $string(160), 'description' => $string(500),
    'amount_mode' => ['type' => 'string', 'enum' => ['FIXED', 'VARIABLE', 'fixed', 'variable']],
    'amount' => $decimal, 'currency' => $string(8), 'maximum_uses' => ['type' => 'integer', 'minimum' => 1],
    'success_url' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 255], 'cancel_url' => ['type' => 'string', 'format' => 'uri', 'maxLength' => 255],
    'expires_at' => ['type' => 'string', 'format' => 'date-time'], 'customer_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
], 'example' => ['title' => 'Sandbox invoice', 'amount_mode' => 'FIXED', 'amount' => '25.00000000', 'currency' => 'USDT']];
$schemas['ExaPayRefundRequest'] = ['type' => 'object', 'additionalProperties' => false, 'required' => ['payment_reference', 'currency', 'reason'], 'properties' => [
    'payment_reference' => $string(160), 'currency' => $string(8), 'reason' => $string(160),
], 'example' => ['payment_reference' => 'PAY-SANDBOX-001', 'currency' => 'USDT', 'reason' => 'Sandbox customer request']];

$schemaExamples = [
    'SpotOrderRequest' => ['symbol' => 'BTC-USDT', 'side' => 'buy', 'type' => 'limit', 'quantity' => '0.00100000', 'price' => '65000.00000000', 'client_order_id' => 'sandbox-spot-001'],
    'FuturesOrderRequest' => ['symbol' => 'BTC-USDT', 'type' => 'limit', 'side' => 'long', 'quantity' => '0.00100000', 'price' => '65000.00000000', 'leverage' => 2],
    'FuturesConditionalOrderRequest' => ['symbol' => 'BTC-USDT', 'type' => 'stop_loss', 'trigger_order_type' => 'market', 'trigger_price' => '62000.00000000', 'quantity' => '0.00100000'],
    'BatchCancelRequest' => ['order_uuids' => ['018f4fd8-4f0a-7d42-9a50-23de9f6c30d2']],
    'FuturesMarginTypeRequest' => ['position_id' => 10, 'margin_type' => 'isolated'],
    'MarginAccountRequest' => ['mode' => 'ISOLATED', 'market_symbol' => 'BTC/USDT'],
    'MarginTransferRequest' => ['account_uuid' => 'margin-sandbox-001', 'direction' => 'IN', 'asset' => 'USDT', 'amount' => '100.00000000', 'idempotency_key' => 'sandbox-margin-transfer-001'],
    'MarginBorrowRequest' => ['account_uuid' => 'margin-sandbox-001', 'asset' => 'USDT', 'amount' => '50.00000000', 'idempotency_key' => 'sandbox-margin-borrow-001'],
    'MarginRepayRequest' => ['amount' => '10.00000000', 'idempotency_key' => 'sandbox-margin-repay-001'],
    'MarginOrderRequest' => ['account_uuid' => 'margin-sandbox-001', 'client_order_id' => 'sandbox-margin-order-001', 'pair' => 'BTC/USDT', 'side' => 'buy', 'type' => 'limit', 'amount' => '0.00100000', 'price' => '65000.00000000'],
    'StakingPositionRequest' => ['staking_product_id' => 1, 'amount' => '100.00000000', 'terms_version' => 'staking-v1', 'idempotency_key' => 'sandbox-stake-001'],
    'StakingUnstakeRequest' => ['amount' => '25.00000000', 'idempotency_key' => 'sandbox-unstake-001'],
    'RealtimeSessionRequest' => ['topics' => ['account.balance', 'order']],
];
foreach ($schemaExamples as $name => $example) {
    $schemas[$name]['example'] = $example;
    $schemas[$name]['additionalProperties'] = false;
}
$schemas['FuturesOrderRequest']['properties'] += [
    'trigger_source' => ['type' => 'string', 'enum' => ['MARK', 'LAST', 'INDEX', 'mark', 'last', 'index']],
    'trailing_distance' => $decimal,
    'metadata' => ['type' => 'object', 'additionalProperties' => true],
];
$schemas['FuturesConditionalOrderRequest']['properties'] += [
    'position_id' => ['type' => 'integer', 'minimum' => 1],
    'metadata' => ['type' => 'object', 'additionalProperties' => true],
];
$schemas['MarginTransferRequest']['properties'] += ['source_account' => $string(64), 'destination_account' => $string(64)];
$schemas['MarginOrderRequest']['properties'] += [
    'time_in_force' => ['type' => 'string', 'enum' => ['GTC', 'IOC', 'FOK', 'gtc', 'ioc', 'fok']],
    'post_only' => ['type' => 'boolean'],
];
$schemas['StakingPositionRequest']['properties'] += ['transaction_pin' => $string(120), 'two_factor_code' => $string(20)];
$schemas['StakingUnstakeRequest']['properties'] += ['transaction_pin' => $string(120), 'two_factor_code' => $string(20)];

$errorResponse = fn (string $description): array => ['description' => $description, 'content' => ['application/json' => ['schema' => ['$ref' => '#/components/schemas/DeveloperError']]]];
$document = [
    'openapi' => '3.1.0',
    'info' => ['title' => 'ExaEarn Developer API', 'version' => '1.0.0', 'description' => 'Generated from registered ExaEarn developer routes. Restricted product status remains independent from documentation completeness.'],
    'servers' => [['url' => 'https://api.exaearn.com', 'description' => 'Production'], ['url' => 'https://sandbox-api.exaearn.com', 'description' => 'Sandbox'], ['url' => 'http://127.0.0.1:8000', 'description' => 'Local']],
    'paths' => $paths,
    'components' => [
        'securitySchemes' => ['ExaSignedRequest' => ['type' => 'apiKey', 'in' => 'header', 'name' => 'EXA-API-KEY', 'description' => 'Also requires EXA-API-TIMESTAMP, EXA-API-NONCE, and EXA-API-SIGNATURE.'],'DeveloperSession'=>['type'=>'apiKey','in'=>'cookie','name'=>'exaearn_session','description'=>'Authenticated canonical ExaEarn Developer session. Sensitive writes also require recent authentication.']],
        'schemas' => $schemas,
        'responses' => [
            'ValidationError' => $errorResponse('Malformed or invalid request.'), 'AuthError' => $errorResponse('Authentication failed.'),
            'PermissionError' => $errorResponse('Scope, IP, environment, or approval denied.'), 'NotFoundError' => $errorResponse('Resource not found.'),
            'ConflictError' => $errorResponse('Idempotency or state conflict.'), 'BusinessRuleError' => $errorResponse('Risk or business rule rejected the operation.'),
            'RateLimitError' => $errorResponse('Rate limit exceeded.'), 'InternalError' => $errorResponse('Safe internal failure without infrastructure disclosure.'),
        ],
    ],
];

file_put_contents($root . '/openapi/exaearn-developer-v1.yaml', json_encode($document, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
$requestContracts = [];
foreach ($paths as $path => $operations) {
    foreach ($operations as $method => $operation) {
        $reference = $operation['requestBody']['content']['application/json']['schema']['$ref'] ?? null;
        if ($reference === null) {
            continue;
        }
        $name = basename($reference);
        $requestContracts[strtoupper($method) . ' ' . $path] = ['schema' => $name] + $schemas[$name];
    }
}
file_put_contents(
    $root . '/apps/developers/src/openapiRequestSchemas.generated.ts',
    "// Generated by scripts/generate-developer-openapi.php. Do not edit manually.\nexport const requestContracts = "
        . json_encode($requestContracts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        . " as const;\n",
);
fwrite(STDOUT, 'Generated ' . count($paths) . " Developer API paths.\n");
