<?php

namespace App\Models;

use App\Enums\InstrumentType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InstrumentSequence extends Model
{
    use HasFactory;

    protected $fillable = [
        'instrument_type',
        'year',
        'current_number',
        'initial_number',
    ];

    protected $casts = [
        'instrument_type' => InstrumentType::class,
        'year' => 'integer',
        'current_number' => 'integer',
        'initial_number' => 'integer',
    ];
}
