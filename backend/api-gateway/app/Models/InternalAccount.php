<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternalAccount extends Model
{
    use HasFactory;
    protected $fillable = [
        'user_id',
        'account_type',
        'account_name',
        'available_balance',
        'locked_balance',
    ];

    protected $casts = [
        'available_balance' => 'decimal:8',
        'locked_balance' => 'decimal:8',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
