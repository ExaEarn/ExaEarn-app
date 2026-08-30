<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class DeveloperOrganizationMembership extends Model
{
    protected $fillable = ['organization_id', 'user_id', 'invited_by', 'role', 'status', 'joined_at'];
    protected $casts = ['joined_at' => 'datetime'];
    public function user(): BelongsTo { return $this->belongsTo(User::class); }
}
