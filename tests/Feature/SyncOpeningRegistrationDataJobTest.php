<?php

namespace Tests\Feature;

use App\Jobs\SyncOpeningRegistrationDataJob;
use App\Models\Opening;
use App\Models\Project;
use App\Models\User;
use App\Services\MapasClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class SyncOpeningRegistrationDataJobTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function fills_opening_registration_data_with_safe_subset_of_mapas_response(): void
    {
        $project = Project::factory()->create();
        $opening = Opening::factory()->create(['project_id' => $project->id]);

        Http::fake([
            '*' => Http::response([
                'registration' => [
                    'id' => 999,
                    'number' => 'on-999',
                    'status' => 10,
                    'files' => ['fileA' => ['id' => 1]],
                    'owner' => ['id' => 42],
                ],
                'fileConfigurations' => ['fileA' => ['label' => 'Documento A']],
                'fields' => [
                    ['titleField' => 'Nome', 'valueField' => 'Fulano de Tal'],
                ],
            ], 200),
        ]);

        (new SyncOpeningRegistrationDataJob(
            projectId: $project->id,
            registrationId: 999,
        ))->handle(app(MapasClient::class));

        $opening->refresh();

        $this->assertSame([
            'registration' => [
                'id' => 999,
                'number' => 'on-999',
                'status' => 10,
                'files' => ['fileA' => ['id' => 1]],
            ],
            'fileConfigurations' => ['fileA' => ['label' => 'Documento A']],
            'fields' => [
                ['titleField' => 'Nome', 'valueField' => 'Fulano de Tal'],
            ],
        ], $opening->registration_data);
    }

    #[Test]
    public function does_not_touch_other_openings(): void
    {
        $project = Project::factory()->create();
        Opening::factory()->create(['project_id' => $project->id]);

        $otherProject = Project::factory()->create();
        $otherOpening = Opening::factory()->create(['project_id' => $otherProject->id]);

        Http::fake([
            '*' => Http::response([
                'registration' => ['id' => 1, 'number' => 'on-1', 'status' => 10, 'files' => []],
                'fileConfigurations' => [],
                'fields' => [],
            ], 200),
        ]);

        (new SyncOpeningRegistrationDataJob(
            projectId: $project->id,
            registrationId: 1,
        ))->handle(app(MapasClient::class));

        $this->assertNull($otherOpening->fresh()->registration_data);
    }

    #[Test]
    public function records_an_audit_entry_attributed_to_the_import_user(): void
    {
        $user = User::factory()->create();
        $project = Project::factory()->create();
        $opening = Opening::factory()->create([
            'project_id' => $project->id,
            'registration_data' => null,
        ]);

        Http::fake([
            '*' => Http::response([
                'registration' => ['id' => 999, 'number' => 'on-999', 'status' => 10, 'files' => []],
                'fileConfigurations' => [],
                'fields' => [],
            ], 200),
        ]);

        (new SyncOpeningRegistrationDataJob(
            projectId: $project->id,
            registrationId: 999,
            userId: $user->id,
        ))->handle(app(MapasClient::class));

        $audit = $opening->audits()->where('event', 'updated')->latest()->first();

        $this->assertNotNull($audit);
        $this->assertSame($user->id, $audit->user_id);
        $this->assertArrayHasKey('registration_data', $audit->new_values);
        $this->assertStringContainsString('mapas-registration-sync', (string) $audit->tags);
    }
}
