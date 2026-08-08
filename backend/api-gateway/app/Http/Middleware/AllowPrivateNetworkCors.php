<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowPrivateNetworkCors
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($request->headers->get('Access-Control-Request-Private-Network') === 'true') {
            $response->headers->set('Access-Control-Allow-Private-Network', 'true');
        }

        return $response;
    }
}
