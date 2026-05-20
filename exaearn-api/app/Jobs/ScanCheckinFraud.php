<?php

namespace App\Jobs;

use App\Models\DailyCheckin;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ScanCheckinFraud implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $checkinId)
    {
    }

    public function handle(): void
    {
        $checkin = DailyCheckin::query()->find($this->checkinId);

        if (! $checkin) {
            return;
        }

        $sameDeviceCount = DailyCheckin::query()
            ->whereDate('checkin_date', $checkin->checkin_date)
            ->where('device_hash', $checkin->device_hash)
            ->distinct('user_id')
            ->count('user_id');

        if ($sameDeviceCount > (int) config('checkin.limits.accounts_per_device')) {
            Log::warning('Suspicious daily check-in device cluster detected', [
                'checkin_id' => $checkin->id,
                'device_hash' => $checkin->device_hash,
                'accounts' => $sameDeviceCount,
            ]);
        }
    }
}
