<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\InstitutionalAccount;
use App\Models\InstitutionalMembership;
use App\Models\InstitutionalSubaccount;
use App\Models\OtcRfq;
use App\Models\OtcTrade;
use App\Services\OtcRfqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

class OtcController extends Controller
{
    public function __construct(private readonly OtcRfqService $otc)
    {
    }

    public function rfqs(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        return response()->json(['data' => OtcRfq::query()->where('institution_id', $institution->id)->with('quotes')->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    public function requestQuote(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate([
            'subaccount_id' => ['required', 'integer'],
            'symbol' => ['required', 'string', 'max:48'],
            'side' => ['required', 'string', 'in:BUY,SELL,buy,sell'],
            'base_amount' => ['required', 'numeric', 'gt:0'],
            'execution_preference' => ['nullable', 'string', 'max:40'],
            'idempotency_key' => ['required', 'string', 'max:160'],
        ]);
        $subaccount = InstitutionalSubaccount::query()->where('institution_id', $institution->id)->findOrFail($payload['subaccount_id']);

        return $this->handle(fn () => $this->otc->requestQuote($request->user(), $institution, $subaccount, $payload), 201);
    }

    public function accept(Request $request, string $rfqUuid): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        $payload = $request->validate(['idempotency_key' => ['required', 'string', 'max:160']]);
        $rfq = OtcRfq::query()->where('institution_id', $institution->id)->where('rfq_uuid', $rfqUuid)->firstOrFail();

        return $this->handle(fn () => $this->otc->accept($request->user(), $rfq, $payload['idempotency_key']), 201);
    }

    public function trades(Request $request): JsonResponse
    {
        $institution = $this->institutionForUser($request);
        return response()->json(['data' => OtcTrade::query()->where('institution_id', $institution->id)->latest()->paginate((int) $request->query('per_page', 25))]);
    }

    private function institutionForUser(Request $request): InstitutionalAccount
    {
        $institution = InstitutionalAccount::query()->where('master_user_id', $request->user()->id)->whereIn('status', ['ACTIVE', 'APPROVED', 'RESTRICTED'])->first();
        if ($institution) {
            return $institution;
        }
        $membership = InstitutionalMembership::query()->where('user_id', $request->user()->id)->where('status', 'ACTIVE')->firstOrFail();
        return InstitutionalAccount::query()->findOrFail($membership->institution_id);
    }

    private function handle(\Closure $callback, int $status = 200): JsonResponse
    {
        try {
            return response()->json(['data' => $callback()], $status);
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
