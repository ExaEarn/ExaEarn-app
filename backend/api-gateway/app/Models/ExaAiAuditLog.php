<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaAiAuditLog extends Model
{
    use HasFactory;

    protected $table = 'exaai_audit_logs';

    public $timestamps = false;

    protected $fillable = [
        'user_id','session_id','event_type','severity','message','context','created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(ExaAiSession::class, 'session_id');
    }
}