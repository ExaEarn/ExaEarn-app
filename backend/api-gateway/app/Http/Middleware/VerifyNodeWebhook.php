<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates incoming webhooks from the Node.js blockchain microservice.
 *
 * The Node.js service must include an X-Webhook-Secret header whose value
 * matches the NODE_SERVICE_SECRET environment variable stored in .env.
 */
class VerifyNodeWebhook
{
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('wallet.node.secret');
        $provided = $request->header('X-Webhook-Secret');

        if (!$expected || !$provided || !hash_equals($expected, $provided)) {
            return response()->json([
                'message' => 'Unauthorized webhook request.',
            ], 401);
        }

        return $next($request);
    }
}
