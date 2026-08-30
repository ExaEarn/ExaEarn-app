<?php
declare(strict_types=1);
namespace App\Http\Controllers\Developer;
use App\Http\Controllers\Controller;
use App\Models\DeveloperProject;
use App\Services\DeveloperProductionAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class DeveloperProductionAccessController extends Controller
{
    public function __construct(private readonly DeveloperProductionAccessService $access){}
    public function show(Request $request,int $projectId): JsonResponse{return response()->json(['data'=>$this->access->status($request->user(),$projectId)]);}
    public function submit(Request $request,int $projectId): JsonResponse
    {
        $payload=$request->validate([
            'use_case'=>['required',Rule::in(['trading_application','trading_bot','portfolio_application','market_data_service','payment_integration','wallet_integration','institutional_integration','fintech_application','internal_company_integration','other'])],
            'capabilities'=>['required','array','min:1'],'capabilities.*'=>['required','string','max:80'],
            'expected_request_volume'=>['nullable','string','max:100'],'expected_trading_volume'=>['nullable','string','max:100'],
            'website'=>['nullable','url','max:255'],'technical_contact'=>['nullable','email','max:255'],'business_context'=>['nullable','string','max:2000'],
            'idempotency_key'=>['required','string','max:100'],
        ]);
        return response()->json(['data'=>$this->access->submit($request->user(),DeveloperProject::query()->findOrFail($projectId),$payload)],201);
    }
}
