<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaAiTermAcceptance extends Model
{
    protected $table = 'exaai_term_acceptances';

    protected $fillable = [
        'user_id',
        'terms_version',
        'acceptance_scope',
        'accepted_at',
        'metadata',
    ];

    protected $casts = [
        'accepted_at' => 'datetime',
        'metadata' => 'array',
    ];
}
