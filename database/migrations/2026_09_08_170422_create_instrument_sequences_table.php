<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('instrument_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('instrument_type', 100);
            $table->integer('year');
            $table->integer('current_number')->default(0);
            $table->integer('initial_number')->default(1);
            $table->timestamps();

            $table->unique(['instrument_type', 'year'], 'unique_instrument_year_seq');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('instrument_sequences');
    }
};
