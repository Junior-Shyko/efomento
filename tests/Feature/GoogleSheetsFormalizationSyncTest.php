<?php

namespace Tests\Feature;

use App\Enums\DeliberationType;
use App\Models\Formalization;
use App\Models\Project;
use App\Models\User;
use App\Services\GoogleSheetsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GoogleSheetsFormalizationSyncTest extends TestCase
{
    use RefreshDatabase;

    private function fakeSheet(string $deliberationCell): void
    {
        $payload = [
            'table' => [
                'cols' => [
                    ['id' => 'A', 'label' => 'CÓDIGO INSCRIÇÃO MAPAS'],
                    ['id' => 'B', 'label' => 'DELIBERAÇÃO'],
                ],
                'rows' => [
                    ['c' => [
                        ['v' => 'INSC-1'],
                        $deliberationCell === '' ? null : ['v' => $deliberationCell],
                    ]],
                ],
            ],
        ];

        Http::fake([
            'docs.google.com/*' => Http::response(
                "/*O_o*/\ngoogle.visualization.Query.setResponse(".json_encode($payload).');'
            ),
        ]);
    }

    private function syncFormalization(): int
    {
        return app(GoogleSheetsService::class)->syncFormalization('sheet-id', 'Formalização', User::factory()->create()->id);
    }

    #[Test]
    public function keeps_existing_deliberation_when_spreadsheet_value_is_invalid(): void
    {
        $project = Project::factory()->create(['number' => 'INSC-1']);
        Formalization::factory()->create([
            'project_id' => $project->id,
            'deliberation' => DeliberationType::BATCH_CGE,
        ]);

        $this->fakeSheet('valor invalido qualquer');

        $this->syncFormalization();

        $this->assertSame(
            DeliberationType::BATCH_CGE,
            $project->formalizations->fresh()->deliberation,
        );
    }

    #[Test]
    public function clears_deliberation_when_spreadsheet_cell_is_blank(): void
    {
        $project = Project::factory()->create(['number' => 'INSC-1']);
        Formalization::factory()->create([
            'project_id' => $project->id,
            'deliberation' => DeliberationType::BATCH_CGE,
        ]);

        $this->fakeSheet('');

        $this->syncFormalization();

        $this->assertNull($project->formalizations->fresh()->deliberation);
    }

    #[Test]
    public function maps_deliberation_label_to_enum(): void
    {
        $project = Project::factory()->create(['number' => 'INSC-1']);
        Formalization::factory()->create([
            'project_id' => $project->id,
            'deliberation' => null,
        ]);

        $this->fakeSheet('Lote CGE');

        $this->syncFormalization();

        $this->assertSame(
            DeliberationType::BATCH_CGE,
            $project->formalizations->fresh()->deliberation,
        );
    }
}
