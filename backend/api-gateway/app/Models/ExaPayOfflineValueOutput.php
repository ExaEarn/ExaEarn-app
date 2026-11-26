<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class ExaPayOfflineValueOutput extends Model
{
    protected $table = 'exapay_offline_value_outputs';

    protected $fillable = ['output_uuid', 'source_instrument_id', 'owner_user_id', 'owner_device_id', 'parent_output_uuid', 'origin_transfer_uuid', 'asset', 'amount_minor', 'hop_count', 'lineage_digest', 'status', 'version'];

    protected $casts = ['amount_minor' => 'integer', 'hop_count' => 'integer', 'version' => 'integer'];
}
