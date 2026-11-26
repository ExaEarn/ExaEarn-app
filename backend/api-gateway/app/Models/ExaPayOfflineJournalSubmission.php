<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ExaPayOfflineJournalSubmission extends Model
{
    protected $table = 'exapay_offline_journal_submissions';

    protected $fillable = ['journal_uuid', 'device_id', 'device_counter', 'previous_digest', 'event_digest', 'nonce_digest', 'payload', 'device_signature', 'status', 'conflict_code', 'submitted_at'];

    protected $casts = ['device_counter' => 'integer', 'payload' => 'array', 'submitted_at' => 'datetime'];

    protected $hidden = ['device_signature'];
}
