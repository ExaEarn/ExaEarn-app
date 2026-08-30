<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DeveloperOrganization extends Model
{
    protected $fillable = ['organization_uuid', 'workspace_id', 'institution_id', 'owner_user_id', 'created_by', 'name', 'slug', 'status', 'verification_status', 'authorized_representative_status', 'production_access_status'];
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function workspace(): BelongsTo { return $this->belongsTo(DeveloperWorkspace::class, 'workspace_id'); }
    public function memberships(): HasMany { return $this->hasMany(DeveloperOrganizationMembership::class, 'organization_id'); }
}
