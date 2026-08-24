<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiNonce;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class DeveloperApiAuth
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        $apiKey = (string) $request->header('EXA-API-KEY', '');
        $timestamp = (string) $request->header('EXA-API-TIMESTAMP', '');
        $signature = (string) $request->header('EXA-API-SIGNATURE', '');
        $passphrase = (string) $request->header('EXA-API-PASSPHRASE', '');
        $nonce = (string) $request->header('EXA-API-NONCE', '');

        if ($apiKey === '' || $timestamp === '' || $signature === '' || $nonce === '') {
            return $this->error('INVALID_API_KEY', 'API key, timestamp, nonce and signature headers are required.', Response::HTTP_UNAUTHORIZED);
        }

        if (abs(time() - (int) $timestamp) > (int) config('developer_api.timestamp_tolerance_seconds', 300)) {
            return $this->error('TIMESTAMP_EXPIRED', 'API request timestamp is outside the allowed window.', Response::HTTP_UNAUTHORIZED);
        }

        $key = DeveloperApiKey::query()
            ->with(['project', 'permissions', 'ipWhitelists'])
            ->where('key_hash', hash('sha256', $apiKey))
            ->where('status', 'active')
            ->first();

        if (! $key || ($key->expires_at && $key->expires_at->isPast())) {
            return $this->error('INVALID_API_KEY', 'API key is invalid or inactive.', Response::HTTP_UNAUTHORIZED);
        }

        if ($key->passphrase_hash && ! hash_equals((string) $key->passphrase_hash, hash('sha256', $passphrase))) {
            return $this->error('INVALID_PASSPHRASE', 'API passphrase is invalid.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->ipAllowed($request->ip() ?: '', $key->ipWhitelists->pluck('cidr')->all())) {
            return $this->error('IP_NOT_ALLOWED', 'This IP address is not allowed for the API key.', Response::HTTP_FORBIDDEN);
        }

        $secret = Crypt::decryptString((string) $key->encrypted_secret);
        $canonical = $this->canonicalPayload($request, $timestamp, $nonce);
        $expected = hash_hmac('sha256', $canonical, $secret);
        if (! hash_equals($expected, $signature)) {
            return $this->error('INVALID_SIGNATURE', 'API request signature is invalid.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            DeveloperApiNonce::query()->create([
                'api_key_id' => $key->id,
                'nonce' => $nonce,
                'seen_at' => now(),
            ]);
        } catch (QueryException) {
            return $this->error('NONCE_REPLAYED', 'API request nonce has already been used.', Response::HTTP_UNAUTHORIZED);
        }

        $granted = $key->permissions->pluck('permission')->all();
        foreach ($permissions as $permission) {
            if (! in_array($permission, $granted, true)) {
                return $this->error('PERMISSION_DENIED', 'API key does not have permission for this endpoint.', Response::HTTP_FORBIDDEN);
            }
        }

        if ($key->subaccount_id !== null) {
            $requestedSubaccount = $request->input('subaccount_id') ?? $request->query('subaccount_id');
            if ($requestedSubaccount !== null && (int) $requestedSubaccount !== (int) $key->subaccount_id) {
                return $this->error('SUBACCOUNT_SCOPE_VIOLATION', 'API key is scoped to a different institutional subaccount.', Response::HTTP_FORBIDDEN);
            }
        }

        $key->forceFill(['last_used_at' => now()])->save();
        $request->attributes->set('developer_api_key', $key);
        $request->attributes->set('developer_project', $key->project);
        $request->attributes->set('institution_id', $key->institution_id);
        $request->attributes->set('institutional_subaccount_id', $key->subaccount_id);
        $request->attributes->set('api_rate_profile', $key->rate_profile);
        $request->setUserResolver(fn () => $key->project?->user ?? \App\Models\User::query()->find($key->user_id));

        return $next($request);
    }

    private function canonicalPayload(Request $request, string $timestamp, string $nonce): string
    {
        $query = $request->getQueryString() ?: '';
        $path = '/' . ltrim($request->path(), '/');
        $bodyHash = hash('sha256', (string) $request->getContent());

        return strtoupper($request->method()) . "\n" . $path . "\n" . $query . "\n" . $timestamp . "\n" . $nonce . "\n" . $bodyHash;
    }

    private function ipAllowed(string $ip, array $cidrs): bool
    {
        if ($cidrs === []) {
            return true;
        }

        foreach ($cidrs as $cidr) {
            if ($cidr === $ip) {
                return true;
            }
            if (str_contains((string) $cidr, '/') && $this->cidrMatch($ip, (string) $cidr)) {
                return true;
            }
        }

        return false;
    }

    private function cidrMatch(string $ip, string $cidr): bool
    {
        [$subnet, $bits] = array_pad(explode('/', $cidr, 2), 2, null);
        if ($bits === null || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false || filter_var($subnet, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        $mask = -1 << (32 - (int) $bits);
        return (ip2long($ip) & $mask) === (ip2long($subnet) & $mask);
    }

    private function error(string $code, string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => request()->attributes->get('request_id'),
            ],
        ], $status);
    }
}
