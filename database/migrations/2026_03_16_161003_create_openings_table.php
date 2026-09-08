<?php

use App\Enums\AccountType;
use App\Enums\AgentStatus;
use App\Enums\OpeningStatus;
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
        Schema::create('openings', function (Blueprint $table) {
            $table->id();

            $table->foreignId('project_id')
                ->unique()
                ->constrained()
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            $table->foreignId('supervisor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('opening_nup')->nullable();
            $table->date('opening_date')->nullable();
            $table->enum('agent_status', array_column(AgentStatus::cases(), 'value'))->nullable();
            $table->string('opened_by')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->index('created_by');

            $table->string('creditor_number')->nullable();

            $table->string('bank')->nullable();
            $table->enum('account_type', array_column(AccountType::cases(), 'value'))->nullable();
            $table->string('branch')->nullable();
            $table->string('account')->nullable();

            $table->boolean('is_draft')->default(true);

            $table->enum('status', array_column(OpeningStatus::cases(), 'value'))
                ->default(OpeningStatus::PENDENTE->value);
            $table->dateTime('certificate_date')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('concluded_at')->nullable();

            $table->jsonb('registration_data')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('openings');
    }
};
