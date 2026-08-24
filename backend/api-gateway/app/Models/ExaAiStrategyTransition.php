<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiStrategyTransition extends Model
{
    protected $table = 'exaai_strategy_transitions';

    protected $fillable = [
        'strategy_version_id',
        'actor_id',
        'previous_state',
        'new_state',
        'reason',
        'metadata',
        'transitioned_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'transitioned_at' => 'datetime',
    ];
}
