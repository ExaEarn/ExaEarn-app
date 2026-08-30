<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
class FinanceJournal extends Model { protected $guarded = []; protected $casts = ['metadata'=>'array','transaction_date'=>'date','posting_date'=>'date','posted_at'=>'datetime']; public function lines(): HasMany { return $this->hasMany(FinanceJournalLine::class); } public function event(): BelongsTo { return $this->belongsTo(FinanceFinancialEvent::class, 'financial_event_id'); } }
