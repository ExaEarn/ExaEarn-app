<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasOne;
class FinanceFinancialEvent extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','economic_at'=>'datetime','amount'=>'decimal:18']; public function journal(): HasOne { return $this->hasOne(FinanceJournal::class, 'financial_event_id'); } }
