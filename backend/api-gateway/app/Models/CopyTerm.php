<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CopyTerm extends Model
{
    protected $fillable = ['type', 'version', 'status', 'summary', 'metadata'];

    protected $casts = [
        'metadata' => 'array',
    ];
}
