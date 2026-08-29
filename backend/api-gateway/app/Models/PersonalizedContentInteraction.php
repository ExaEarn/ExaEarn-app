<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PersonalizedContentInteraction extends Model
{
    protected $fillable = ['content_id', 'user_id', 'interaction_type', 'surface', 'position', 'event_uuid', 'metadata'];
    protected function casts(): array { return ['metadata' => 'array']; }
}
