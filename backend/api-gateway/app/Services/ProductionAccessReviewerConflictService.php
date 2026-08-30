<?php
declare(strict_types=1);
namespace App\Services;
use App\Models\Admin;use App\Models\DeveloperProductionAccessRequest;use App\Models\InstitutionalAccount;use Illuminate\Auth\Access\AuthorizationException;
class ProductionAccessReviewerConflictService
{
 public function conflict(Admin $admin,DeveloperProductionAccessRequest $request):?string
 {
  if(!$admin->canonical_user_id)return 'REVIEWER_IDENTITY_UNLINKED';
  $conflicts=[(int)$request->submitted_by];$project=$request->project;
  if($project?->organization_id){$conflicts[]=(int)$project->organization?->owner_user_id;$institution=$project->organization?->institution_id?InstitutionalAccount::query()->find($project->organization->institution_id):null;if($institution)$conflicts[]=(int)$institution->master_user_id;}
  return in_array((int)$admin->canonical_user_id,array_unique($conflicts),true)?'APPLICANT_REVIEWER_CONFLICT':null;
 }
 public function assertIndependent(Admin $admin,DeveloperProductionAccessRequest $request):void
 {
  $conflict=$this->conflict($admin,$request);
  if($conflict==='REVIEWER_IDENTITY_UNLINKED')throw new AuthorizationException('Reviewer canonical identity is required.');
  if($conflict)throw new AuthorizationException('Reviewer conflicts with the Production Access applicant.');
 }
}
