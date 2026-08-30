<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\DeveloperApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\Services\Security\CanonicalClientIp;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class DeveloperApiRequestContext
{
    public function __construct(private readonly CanonicalClientIp $clientIp) {}
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = 'exa_req_' . Str::lower(Str::random(24));
        $request->attributes->set('request_id', $requestId);
        $started = microtime(true);

        try {
            /** @var Response $response */
            $response = $next($request);
        } catch (Throwable $exception) {
            report($exception);
            $this->record($request,$requestId,$started,500,'INTERNAL_ERROR',get_class($exception));
            return response()->json(['success'=>false,'error'=>['code'=>'INTERNAL_ERROR','message'=>'The request could not be completed.','request_id'=>$requestId,'details'=>(object)[]],'timestamp'=>now()->getTimestampMs()],500)->withHeaders(['X-Exa-Request-Id'=>$requestId]);
        }
        $response->headers->set('X-Exa-Request-Id', $requestId);
        $errorCode=data_get(json_decode((string)$response->getContent(),true),'error.code');
        if($response->getStatusCode()>=500 && !is_string($errorCode))$errorCode='INTERNAL_ERROR';
        $this->record($request,$requestId,$started,$response->getStatusCode(),is_string($errorCode)?$errorCode:null,null);
        return $response;
    }

    private function record(Request $request,string $requestId,float $started,int $status,?string $errorCode,?string $exceptionClass): void
    {
        $apiKey=$request->attributes->get('developer_api_key');$project=$request->attributes->get('developer_project');
        try{DeveloperApiRequestLog::query()->create([
            'request_id' => $requestId,
            'user_id' => $apiKey?->user_id,
            'project_id' => $project?->id,
            'api_key_id' => $apiKey?->id,
            'environment' => $apiKey?->environment,
            'method' => $request->method(),
            'path' => '/' . ltrim($request->path(), '/'),
            'status_code' => $status,
            'latency_ms' => (int) round((microtime(true) - $started) * 1000),
            'ip_address' => $this->clientIp->for($request),
            'error_code' => $errorCode,
            'metadata' => array_filter(['user_agent'=>$request->userAgent(),'credential_uuid'=>$apiKey?->key_uuid,'workspace_id'=>$project?->workspace_id,'organization_id'=>$project?->organization_id,'exception_category'=>$exceptionClass]),
        ]);}catch(Throwable $loggingFailure){report($loggingFailure);}
    }
}
