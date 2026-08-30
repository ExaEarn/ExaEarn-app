<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeveloperApiKey;
use App\Models\DeveloperApiNonce;
use App\Models\DeveloperAuditLog;
use App\Services\DeveloperApiSignatureService;
use App\Services\DeveloperProductionAccessService;
use App\Services\Security\CanonicalClientIp;
use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\IpUtils;

class DeveloperApiAuth
{
    public function __construct(private readonly DeveloperApiSignatureService $signatures, private readonly DeveloperProductionAccessService $productionAccess, private readonly CanonicalClientIp $clientIp) {}

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
            ->with(['project.environments', 'project.workspace', 'permissions', 'ipWhitelists'])
            ->where('key_hash', hash('sha256', $apiKey))
            ->where('status', 'active')
            ->first();

        if (! $key || ($key->expires_at && $key->expires_at->isPast())) {
            return $this->error('INVALID_API_KEY', 'API key is invalid or inactive.', Response::HTTP_UNAUTHORIZED);
        }
        $request->attributes->set('developer_api_key',$key);
        $request->attributes->set('developer_project',$key->project);

        if (!$key->project || $key->project->status !== 'active' || $key->project->workspace?->status !== 'active') {
            return $this->error('PROJECT_INACTIVE', 'The owning Developer project is inactive.', Response::HTTP_FORBIDDEN);
        }
        if ($key->project->organization_id === null) {
            $owner = $key->project->user;
            if (!$owner || !in_array(strtoupper((string)($owner->account_status ?: 'FULLY_ACTIVE')),['ACTIVE','FULLY_ACTIVE'],true)) {
                return $this->error('ACCOUNT_INACTIVE','The owning ExaEarn account is inactive.',Response::HTTP_FORBIDDEN);
            }
        } elseif (!$key->project->organization || $key->project->organization->status !== 'active') {
            return $this->error('ORGANIZATION_INACTIVE','The owning Developer organization is inactive.',Response::HTTP_FORBIDDEN);
        }
        $environment = $key->project->environments->firstWhere('type', $key->environment);
        if (!$environment || $environment->status !== 'active') {
            return $this->error('ENVIRONMENT_INACTIVE', 'The API key environment is not active.', Response::HTTP_FORBIDDEN);
        }
        if ($key->environment !== strtolower((string)config('developer_api.runtime_environment','sandbox'))) {
            return $this->error('ENVIRONMENT_MISMATCH','This credential cannot authenticate against this environment.',Response::HTTP_FORBIDDEN);
        }

        if ($key->passphrase_hash && ! hash_equals((string) $key->passphrase_hash, hash('sha256', $passphrase))) {
            return $this->error('INVALID_PASSPHRASE', 'API passphrase is invalid.', Response::HTTP_UNAUTHORIZED);
        }

        if (! $this->ipAllowed($this->clientIp->for($request), $key->ipWhitelists->pluck('cidr')->all())) {
            $this->auditFailure($key,'IP_NOT_ALLOWED',$request);
            return $this->error('IP_NOT_ALLOWED', 'This IP address is not allowed for the API key.', Response::HTTP_FORBIDDEN);
        }

        $secret = Crypt::decryptString((string) $key->encrypted_secret);
        $expected = $this->signatures->sign($secret,$request->method(),'/'.ltrim($request->path(),'/'),$request->getQueryString() ?: '',$timestamp,$nonce,(string)$request->getContent());
        if (! hash_equals($expected, $signature)) {
            $this->auditFailure($key,'INVALID_SIGNATURE',$request);
            return $this->error('INVALID_SIGNATURE', 'API request signature is invalid.', Response::HTTP_UNAUTHORIZED);
        }

        try {
            DeveloperApiNonce::query()->create([
                'api_key_id' => $key->id,
                'nonce' => $nonce,
                'seen_at' => now(),
            ]);
        } catch (QueryException) {
            $this->auditFailure($key,'NONCE_REPLAYED',$request);
            return $this->error('NONCE_REPLAYED', 'API request nonce has already been used.', Response::HTTP_UNAUTHORIZED);
        }

        $granted = $key->permissions->pluck('permission')->all();
        foreach ($permissions as $permission) {
            if (! in_array($permission, $granted, true)) {
                $this->auditFailure($key,'PERMISSION_DENIED',$request);
                return $this->error('PERMISSION_DENIED', 'API key does not have permission for this endpoint.', Response::HTTP_FORBIDDEN);
            }
        }
        if ($key->environment === 'production') {
            try {
                $this->productionAccess->assertCapabilities($key->project, $permissions);
            } catch (\RuntimeException $exception) {
                $code=in_array($exception->getMessage(),['CAPABILITY_NOT_APPROVED','JURISDICTION_RESTRICTED','VERIFICATION_REQUIRED','PRODUCTION_ACCESS_REQUIRED'],true)?$exception->getMessage():'PRODUCTION_ACCESS_REQUIRED';
                return $this->error($code, 'Production authorization is unavailable for this request.', Response::HTTP_FORBIDDEN);
            }
        }

        [$rateKey,$rateLimit]=$this->ratePolicy($request,$key->id,$key->environment,$permissions);
        if(RateLimiter::tooManyAttempts($rateKey,$rateLimit)){
            $retry=RateLimiter::availableIn($rateKey);
            return $this->error('RATE_LIMITED','Developer API credential rate limit exceeded.',Response::HTTP_TOO_MANY_REQUESTS)
                ->withHeaders(['Retry-After'=>(string)$retry,'X-RateLimit-Limit'=>(string)$rateLimit,'X-RateLimit-Remaining'=>'0']);
        }
        RateLimiter::hit($rateKey,60);

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

        $response=$next($request);
        $response->headers->set('X-RateLimit-Limit',(string)$rateLimit);
        $response->headers->set('X-RateLimit-Remaining',(string)max(0,RateLimiter::remaining($rateKey,$rateLimit)));
        return $response;
    }

    private function ratePolicy(Request $request,int $keyId,string $environment,array $permissions): array
    {
        $class=str_contains($request->path(),'withdraw')?'withdrawal':(collect($permissions)->contains(fn(string $scope)=>str_contains($scope,'trade')||str_contains($scope,'execute')||str_contains($scope,'write'))?'trade':'private');
        $limit=(int)config("developer_api.rate_limits.{$class}_per_minute",config('developer_api.rate_limits.private_per_minute',120));
        return ["developer-api:{$environment}:{$keyId}:{$class}",$limit];
    }

    private function auditFailure(DeveloperApiKey $key,string $code,Request $request): void
    {
        DeveloperAuditLog::query()->create(['user_id'=>$key->user_id,'project_id'=>$key->project_id,'api_key_id'=>$key->id,'event_type'=>'developer.api_key.authentication_failed_security','severity'=>'warning','message'=>'Developer API authentication or authorization failed.','context'=>['code'=>$code,'key_uuid'=>$key->key_uuid,'environment'=>$key->environment,'path'=>'/'.ltrim($request->path(),'/'),'ip'=>$this->clientIp->for($request)],'created_at'=>now()]);
    }

    private function ipAllowed(string $ip, array $cidrs): bool
    {
        if ($cidrs === []) {
            return true;
        }

        foreach ($cidrs as $cidr) {
            if (IpUtils::checkIp($ip,(string)$cidr)) return true;
        }

        return false;
    }

    private function error(string $code, string $message, int $status): Response
    {
        return response()->json([
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'request_id' => request()->attributes->get('request_id'),
                'details' => (object) [],
            ],
            'timestamp' => now()->getTimestampMs(),
        ], $status);
    }
}
