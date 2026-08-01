<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsApplication extends Model
{
    protected $fillable = ['opportunity_id', 'user_id', 'cover_note', 'portfolio_url', 'status'];
}
