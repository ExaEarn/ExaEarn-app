<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class KbArticleVersion extends Model
{
    protected $guarded = [];
    protected $casts = ['keywords' => 'array'];
}
