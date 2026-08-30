<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class DeveloperWorkspace extends Model
{
    protected $fillable = ['workspace_uuid','type','owner_user_id','name','status'];
    public function projects(): HasMany { return $this->hasMany(DeveloperProject::class, 'workspace_id'); }
}
