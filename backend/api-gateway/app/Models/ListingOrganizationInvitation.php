<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
class ListingOrganizationInvitation extends Model { protected $guarded = []; protected $hidden = ['token_hash']; protected $casts = ['expires_at'=>'datetime','accepted_at'=>'datetime','revoked_at'=>'datetime']; public function organization(): BelongsTo { return $this->belongsTo(ListingOrganization::class, 'organization_id'); } }
