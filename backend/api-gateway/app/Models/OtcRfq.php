<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class OtcRfq extends Model { protected $guarded = []; protected $casts = ['base_amount'=>'decimal:18','quote_amount'=>'decimal:18','settlement_amount'=>'decimal:18','expires_at'=>'datetime','eligibility_snapshot'=>'array','risk_snapshot'=>'array','metadata'=>'array']; public function quotes(): HasMany { return $this->hasMany(OtcQuote::class, 'rfq_id'); } }
