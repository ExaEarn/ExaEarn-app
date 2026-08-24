<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaAiStrategyVersion extends Model
{
    use HasFactory;

    protected $table = 'exaai_strategy_versions';

    protected $fillable = [
        'strategy_definition_id','version','is_current','config','risk_rules','notes','published_at',
        'state','supported_products','supported_markets','activated_at','retired_at',
    ];

    protected $casts = [
        'is_current' => 'boolean',
        'config' => 'array',
        'risk_rules' => 'array',
        'supported_products' => 'array',
        'supported_markets' => 'array',
        'published_at' => 'datetime',
        'activated_at' => 'datetime',
        'retired_at' => 'datetime',
    ];

    public function definition(): BelongsTo
    {
        return $this->belongsTo(ExaAiStrategyDefinition::class, 'strategy_definition_id');
    }
}
