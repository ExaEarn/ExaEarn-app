<?php

declare(strict_types=1);

namespace App\Services\Spot;

use App\Models\Market;
use App\Models\SpotEngineSequence;
use Illuminate\Support\Facades\DB;

class SpotSequencer
{
    public function next(Market $market): int
    {
        return DB::transaction(function () use ($market): int {
            $row = SpotEngineSequence::query()
                ->where('market_id', $market->id)
                ->lockForUpdate()
                ->first();

            if (!$row) {
                $row = SpotEngineSequence::query()->create([
                    'market_id' => $market->id,
                    'market_symbol' => $market->symbol,
                    'last_sequence' => 0,
                ]);
                $row = SpotEngineSequence::query()->whereKey($row->id)->lockForUpdate()->firstOrFail();
            }

            $row->last_sequence = ((int) $row->last_sequence) + 1;
            $row->market_symbol = $market->symbol;
            $row->save();

            return (int) $row->last_sequence;
        });
    }
}
