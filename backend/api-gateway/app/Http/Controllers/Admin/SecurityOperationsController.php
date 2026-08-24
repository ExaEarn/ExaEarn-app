<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\SecurityCase;
use App\Models\SecurityEmergencyControl;
use App\Models\SecurityIncident;
use App\Models\SecurityRiskDecision;
use App\Models\SecurityRiskSignal;
use App\Services\SecurityCaseService;
use App\Services\SecurityEmergencyControlService;
use App\Services\SecurityIncidentService;
use App\Services\SecurityReadinessService;
use App\Services\SecurityRiskEngine;
use App\Services\SecurityRuleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SecurityOperationsController extends Controller
{
    public function __construct(
        private readonly SecurityRiskEngine $risk,
        private readonly SecurityCaseService $cases,
        private readonly SecurityIncidentService $incidents,
        private readonly SecurityEmergencyControlService $emergency,
        private readonly SecurityRuleService $rules,
        private readonly SecurityReadinessService $readiness,
    ) {
    }

    public function overview(): JsonResponse
    {
        return response()->json(['data' => [
            'readiness' => $this->readiness->evaluate(),
            'active_signals' => SecurityRiskSignal::query()->where('status', 'ACTIVE')->count(),
            'open_cases' => SecurityCase::query()->whereNotIn('status', ['RESOLVED', 'CLOSED', 'FALSE_POSITIVE'])->count(),
            'active_incidents' => SecurityIncident::query()->whereNotIn('status', ['RESOLVED', 'POSTMORTEM'])->count(),
            'active_emergency_controls' => SecurityEmergencyControl::query()->where('status', 'ACTIVE')->count(),
        ]]);
    }

    public function evaluate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'subject_type' => ['required', 'string', 'max:64'],
            'subject_id' => ['nullable', 'integer'],
            'action' => ['required', 'string', 'max:96'],
            'context' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $this->risk->evaluate($data['subject_type'], $data['subject_id'] ?? null, $data['action'], $data['context'] ?? [])]);
    }

    public function cases(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'case_type' => ['required', 'string', 'max:80'],
                'severity' => ['required', Rule::in(['LOW', 'MEDIUM', 'HIGH', 'CRITICAL'])],
                'subject_type' => ['nullable', 'string', 'max:64'],
                'subject_id' => ['nullable', 'integer'],
                'evidence' => ['nullable', 'array'],
            ]);
            return response()->json(['data' => $this->cases->create($data['case_type'], $data['severity'], $data['subject_type'] ?? null, $data['subject_id'] ?? null, $data['evidence'] ?? [], $this->admin($request))], 201);
        }

        return response()->json(['data' => SecurityCase::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function incidents(Request $request): JsonResponse
    {
        if ($request->isMethod('post')) {
            $data = $request->validate([
                'category' => ['required', 'string', 'max:80'],
                'severity' => ['required', Rule::in(['SEV1', 'SEV2', 'SEV3', 'SEV4'])],
                'scope_type' => ['nullable', 'string', 'max:64'],
                'scope_reference' => ['nullable', 'string', 'max:180'],
                'impact' => ['nullable', 'array'],
            ]);
            return response()->json(['data' => $this->incidents->create($data['category'], $data['severity'], $data['scope_type'] ?? 'GLOBAL', $data['scope_reference'] ?? null, $data['impact'] ?? [])], 201);
        }

        return response()->json(['data' => SecurityIncident::query()->latest()->paginate((int) $request->query('per_page', 50))]);
    }

    public function emergency(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate([
            'control_type' => ['required', 'string', 'max:80'],
            'scope_type' => ['required', 'string', 'max:64'],
            'scope_reference' => ['nullable', 'string', 'max:180'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
            'metadata' => ['nullable', 'array'],
        ]);

        return response()->json(['data' => $this->emergency->activate($admin, $data['control_type'], $data['scope_type'], $data['scope_reference'] ?? null, $data['reason'], $data['metadata'] ?? [])], 201);
    }

    public function rules(Request $request): JsonResponse
    {
        $admin = $this->admin($request);
        $data = $request->validate([
            'rule_key' => ['required', 'string', 'max:120'],
            'mode' => ['required', Rule::in(['SHADOW', 'ACTIVE', 'DISABLED'])],
            'configuration' => ['required', 'array'],
            'reason' => ['required', 'string', 'min:8', 'max:1000'],
        ]);

        return response()->json(['data' => $this->rules->change($admin, $data['rule_key'], $data['configuration'], $data['reason'], $data['mode'])], 201);
    }

    private function admin(Request $request): Admin
    {
        $admin = $request->user();
        abort_unless($admin instanceof Admin && $admin->hasPermission('security.operations'), 403);

        return $admin;
    }
}
