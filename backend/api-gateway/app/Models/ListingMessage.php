<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ListingMessage extends Model
{
    protected $fillable = ['message_uuid', 'application_id', 'sender_type', 'sender_id', 'message_type', 'subject', 'body', 'internal_only'];

    protected $casts = ['internal_only' => 'boolean'];
}
