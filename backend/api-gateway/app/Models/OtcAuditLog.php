<?php
declare(strict_types=1);
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class OtcAuditLog extends Model { protected $guarded = []; protected $casts = ['before'=>'array','after'=>'array']; }
