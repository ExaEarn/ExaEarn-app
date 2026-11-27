<?php

namespace App\Http\Middleware;

use App\Support\StagingSafety;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class StagingSafetyMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (StagingSafety::enabled() && ! in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return response()->json([
                'message' => 'State-changing operations are disabled in staging.',
                'code' => 'STAGING_FINANCIAL_SAFETY_LOCK',
            ], Response::HTTP_FORBIDDEN);
        }

        return $next($request);
    }
}
