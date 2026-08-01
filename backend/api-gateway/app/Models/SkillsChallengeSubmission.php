<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SkillsChallengeSubmission extends Model
{
    protected $fillable = ['challenge_id', 'user_id', 'description', 'repository_url', 'demo_url', 'attachments', 'status'];

    protected $casts = ['attachments' => 'array'];
}
