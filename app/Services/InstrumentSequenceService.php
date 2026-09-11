<?php

namespace App\Services;

use App\Enums\InstrumentType;
use App\Models\InstrumentSequence;
use Illuminate\Support\Facades\DB;

class InstrumentSequenceService
{
    public function generateNextTermNumber(InstrumentType $instrumentType, ?int $year = null): string
    {
        $year = $year ?? (int) now()->format('Y');

        return DB::transaction(function () use ($instrumentType, $year) {
            // Garante de forma atômica no banco que a linha base existe, ignorando conflito se outra requisição acabou de criá-la
            InstrumentSequence::insertOrIgnore([
                'instrument_type' => $instrumentType->value,
                'year' => $year,
                'current_number' => 0,
                'initial_number' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $sequence = InstrumentSequence::where('instrument_type', $instrumentType->value)
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            $sequence->increment('current_number');

            return "{$sequence->current_number}/{$year}";
        });
    }
}
