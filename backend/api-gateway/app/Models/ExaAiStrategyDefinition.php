<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaAiStrategyDefinition extends Model
{
    use HasFactory;

    protected $table = 'exaai_strategy_definitions';

    protected $fillable = [
        'code','name','risk_level','description','supports_spot','supports_futures','is_active',
        'allowed_plan_codes','default_constraints',
    ];

    protected $casts = [
        'supports_spot' => 'boolean',
        'supports_futures' => 'boolean',
        'is_active' => 'boolean',
        'allowed_plan_codes' => 'array',
        'default_constraints' => 'array',
    ];

    public function versions(): HasMany
    {
        return $this->hasMany(ExaAiStrategyVersion::class, 'strategy_definition_id');
    }
}