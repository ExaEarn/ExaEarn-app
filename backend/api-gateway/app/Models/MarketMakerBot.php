<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
class MarketMakerBot extends Model { protected $guarded = []; protected $casts = ['configuration'=>'array','risk_limits'=>'array','last_heartbeat_at'=>'datetime','worker_lease_expires_at'=>'datetime','approved_at'=>'datetime']; public function quoteCycles(): HasMany { return $this->hasMany(MarketMakerBotQuoteCycle::class, 'bot_id'); } }
