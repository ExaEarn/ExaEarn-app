<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeveloperApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class DeveloperApiRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = 'exa_req_' . Str::lower(Str::random(24));
        $request->attributes->set('request_id', $requestId);
        $started = microtime(true);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Exa-Request-Id', $requestId);

        $apiKey = $request->attributes->get('developer_api_key');
        $project = $request->attributes->get('developer_project');
        DeveloperApiRequestLog::query()->create([
            'request_id' => $requestId,
            'user_id' => $apiKey?->user_id,
            'project_id' => $project?->id,
            'api_key_id' => $apiKey?->id,
            'environment' => $project?->environment,
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'status_code' => $response->getStatusCode(),
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'ip_address' => $request->ip(),
            'error_code' => data_get(json_decode((string) $response->getContent(), true), 'error.code'),
            'metadata' => ['user_agent' => $request->userAgent()],
        ]);

        return $response;
    }
}
