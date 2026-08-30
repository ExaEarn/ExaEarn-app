<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DeveloperApiRealtimeSession extends Model
{
    protected $fillable=['session_uuid','api_key_id','project_id','environment','token_hash','status','topics','expires_at','revoked_at'];
    protected $casts=['topics'=>'array','expires_at'=>'datetime','revoked_at'=>'datetime'];
    protected $hidden=['token_hash'];
    public function apiKey(): BelongsTo { return $this->belongsTo(DeveloperApiKey::class,'api_key_id'); }
    public function project(): BelongsTo { return $this->belongsTo(DeveloperProject::class,'project_id'); }
}
