<?php
declare(strict_types=1);
namespace App\Http\Controllers\Admin;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\DeveloperProductionAccessRequest;
use App\Services\DeveloperProductionAccessService;
use App\Services\ProductionAccessReviewerConflictService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
class DeveloperProductionAccessController extends Controller
{
    public function __construct(private readonly DeveloperProductionAccessService $access,private readonly ProductionAccessReviewerConflictService $conflicts){}
    public function index(Request $request): JsonResponse
    {
        $admin=$request->user();abort_unless($admin instanceof Admin && $admin->hasPermission('developer.production.read'),403);
        $query=DeveloperProductionAccessRequest::query()->with(['project.workspace','project.organization','capabilities'])
            ->when($request->query('status'),fn($q,$s)=>$q->where('status',$s))
            ->when($request->query('applicant_type'),fn($q,$s)=>$q->where('applicant_type',$s))
            ->when($request->query('jurisdiction'),fn($q,$s)=>$q->where('jurisdiction',strtoupper((string)$s)))
            ->when($request->query('capability'),fn($q,$s)=>$q->whereHas('capabilities',fn($c)=>$c->where('capability',$s)))
            ->when($request->query('search'),function($q,$search){$term='%'.trim((string)$search).'%';$q->where(function($nested)use($term){$nested->where('request_uuid','like',$term)->orWhereHas('project',fn($p)=>$p->where('name','like',$term));});});
        return response()->json(['data'=>$query->latest()->paginate(min(100,max(1,(int)$request->query('per_page',25))))]);
    }
    public function show(Request $request,string $uuid): JsonResponse
    {
        $admin=$request->user();abort_unless($admin instanceof Admin && $admin->hasPermission('developer.production.read'),403);
        $row=DeveloperProductionAccessRequest::query()->with(['project.workspace','project.organization','capabilities.reviews','reviews'])->where('request_uuid',$uuid)->firstOrFail();
        $row->reviews->each->makeVisible('internal_note');$row->capabilities->each(fn($capability)=>$capability->reviews->each->makeVisible('internal_note'));
        return response()->json(['data'=>$row,'reviewer_conflict'=>$this->conflicts->conflict($admin,$row)]);
    }
    public function decide(Request $request,string $uuid): JsonResponse
    {
        $payload=$request->validate(['action'=>['required',Rule::in(['approve','partial_approve','action_required','reject','suspend','resume','revoke'])],'capabilities'=>['nullable','array'],'capabilities.*'=>[Rule::in(['approved','restricted','rejected','suspended','revoked'])],'limits'=>['nullable','array'],'public_message'=>['nullable','string','max:2000'],'internal_note'=>['nullable','string','max:5000'],'expected_version'=>['nullable','integer','min:1'],'idempotency_key'=>['required','string','max:100']]);
        return response()->json(['data'=>$this->access->decide($request->user(),DeveloperProductionAccessRequest::query()->where('request_uuid',$uuid)->firstOrFail(),$payload)]);
    }
}
