<?php

namespace Tests\Feature;

use App\Enums\DocumentPhase;
use App\Enums\DocumentType;
use App\Enums\ProjectStageSlug;
use App\Enums\ProjectStageStatus;
use App\Models\Document;
use App\Models\Formalization;
use App\Models\Notice;
use App\Models\Opening;
use App\Models\OpeningSupervisor;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProjectStageControllerTest extends TestCase
{
    use RefreshDatabase;

    private function createUserWithRoles(string ...$roles): User
    {
        $user = User::factory()->create();
        foreach ($roles as $role) {
            $user->assignRole(Role::firstOrCreate(['name' => $role, 'guard_name' => 'web']));
        }

        return $user;
    }

    /**
     * Aprova as etapas anteriores e coloca a etapa do slug informado em andamento.
     */
    private function activateStage(Project $project, ProjectStageSlug $slug): ProjectStage
    {
        $stage = $project->stages()->where('slug', $slug->value)->firstOrFail();

        $project->stages()
            ->where('order', '<', $stage->order)
            ->update([
                'status' => ProjectStageStatus::APROVADO->value,
                'started_at' => now()->subDay(),
                'concluded_at' => now(),
            ]);

        $stage->update([
            'status' => ProjectStageStatus::EM_ANDAMENTO->value,
            'started_at' => now(),
        ]);

        return $stage->fresh();
    }

    private function createCompleteOpening(Project $project): Opening
    {
        $opening = Opening::factory()->create(['project_id' => $project->id]);

        $principal = User::factory()->create();
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $principal->id,
            'type' => 'principal',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $alternate = User::factory()->create();
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $alternate->id,
            'type' => 'alternate',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        Formalization::factory()->create([
            'project_id' => $project->id,
            'report_status' => 'REGULAR E_ADIMPLENTE',
            'eparcerias_certificate_date' => now()->toDateString(),
        ]);

        return $opening;
    }

    private function createCompleteFormalization(Project $project): Formalization
    {
        $formalization = Formalization::factory()->create(['project_id' => $project->id]);

        foreach (DocumentType::requiredForFormalizationAdvance() as $type) {
            Document::factory()->create([
                'project_id' => $project->id,
                'notice_id' => $project->notice_id,
                'type' => $type,
                'phase' => DocumentPhase::FORMALIZATION,
            ]);
        }

        return $formalization;
    }

    public function test_cannot_advance_formalization_stage_without_formalization_data(): void
    {
        $project = Project::factory()->create();
        $stage = $this->activateStage($project, ProjectStageSlug::FORMALIZACAO);
        $user = $this->createUserWithRoles('legal_analysis');

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHasErrors(['formalization']);

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $stage->fresh()->status);
    }

    public function test_cannot_advance_formalization_stage_without_required_documents(): void
    {
        $project = Project::factory()->create();
        $stage = $this->activateStage($project, ProjectStageSlug::FORMALIZACAO);
        Formalization::factory()->create(['project_id' => $project->id]);
        $user = $this->createUserWithRoles('legal_analysis');

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHasErrors(['documents']);

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $stage->fresh()->status);
    }

    public function test_advances_formalization_stage_when_requirements_are_met(): void
    {
        $project = Project::factory()->create();
        $stage = $this->activateStage($project, ProjectStageSlug::FORMALIZACAO);
        $this->createCompleteFormalization($project);
        $user = $this->createUserWithRoles('legal_analysis');

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertRedirect(route('notices.projects', [
                'notice' => $project->notice_id,
                'phase' => ProjectStageSlug::FORMALIZACAO->value,
            ]))
            ->assertSessionHas('success', 'Processo tramitado com sucesso!');

        $this->assertEquals(ProjectStageStatus::APROVADO, $stage->fresh()->status);

        $nextStage = $project->stages()
            ->where('slug', ProjectStageSlug::ORCAMENTO->value)
            ->first();

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $nextStage->status);
    }

    public function test_advances_abertura_stage_when_requirements_are_met(): void
    {
        $project = Project::factory()->create();
        $stage = $project->stages()
            ->where('slug', ProjectStageSlug::ABERTURA->value)
            ->firstOrFail();
        $this->createCompleteOpening($project);
        $user = $this->createUserWithRoles('fomentation');

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertRedirect(route('notices.projects', [
                'notice' => $project->notice_id,
                'phase' => ProjectStageSlug::ABERTURA->value,
            ]))
            ->assertSessionHas('success');

        $this->assertEquals(ProjectStageStatus::APROVADO, $stage->fresh()->status);
    }

    public function test_cannot_advance_abertura_stage_without_opening_data(): void
    {
        $project = Project::factory()->create();
        $stage = $project->stages()
            ->where('slug', ProjectStageSlug::ABERTURA->value)
            ->firstOrFail();
        $user = $this->createUserWithRoles('fomentation');

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHasErrors(['opening']);

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $stage->fresh()->status);
    }

    public function test_cannot_advance_abertura_stage_without_principal_supervisor(): void
    {
        $project = Project::factory()->create();
        $stage = $project->stages()
            ->where('slug', ProjectStageSlug::ABERTURA->value)
            ->firstOrFail();
        Opening::factory()->create(['project_id' => $project->id]);
        Formalization::factory()->create([
            'project_id' => $project->id,
            'report_status' => 'REGULAR E_ADIMPLENTE',
            'eparcerias_certificate_date' => now()->toDateString(),
        ]);
        $user = $this->createUserWithRoles('fomentation');

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHasErrors(['opening']);

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $stage->fresh()->status);
    }

    public function test_advances_abertura_stage_without_alternate_supervisor(): void
    {
        $project = Project::factory()->create();
        $stage = $project->stages()
            ->where('slug', ProjectStageSlug::ABERTURA->value)
            ->firstOrFail();
        $opening = Opening::factory()->create(['project_id' => $project->id]);
        $supervisor = User::factory()->create();
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $supervisor->id,
            'type' => 'principal',
            'is_active' => true,
            'assigned_at' => now(),
        ]);
        Formalization::factory()->create([
            'project_id' => $project->id,
            'report_status' => 'REGULAR E_ADIMPLENTE',
            'eparcerias_certificate_date' => now()->toDateString(),
        ]);
        $user = $this->createUserWithRoles('fomentation');

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHas('success');

        $this->assertEquals(ProjectStageStatus::APROVADO, $stage->fresh()->status);
    }

    public function test_advance_returns_error_message_when_user_lacks_role(): void
    {
        $project = Project::factory()->create();
        $stage = $project->stages()
            ->where('slug', ProjectStageSlug::ABERTURA->value)
            ->firstOrFail();
        $this->createCompleteOpening($project);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHasErrors(['message' => 'Você não tem permissão para tramitar esta etapa.']);

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $stage->fresh()->status);
    }

    public function test_advance_returns_specific_message_when_stage_not_em_andamento(): void
    {
        $project = Project::factory()->create();
        $stage = $project->stages()
            ->where('slug', ProjectStageSlug::ANALISE_JURIDICA->value)
            ->firstOrFail();
        $user = $this->createUserWithRoles('legal_analysis');

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHasErrors(['message' => 'A etapa precisa estar em andamento para ser aprovada.']);
    }

    public function test_advance_prioritizes_status_error_over_role_error(): void
    {
        $project = Project::factory()->create();
        $stage = $project->stages()
            ->where('slug', ProjectStageSlug::ANALISE_JURIDICA->value)
            ->firstOrFail();
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHasErrors(['message' => 'A etapa precisa estar em andamento para ser aprovada.']);
    }

    public function test_cannot_advance_monitoring_stage_when_user_is_not_principal_supervisor(): void
    {
        $project = Project::factory()->create();
        $stage = $this->activateStage($project, ProjectStageSlug::MONITORAMENTO);

        $opening = Opening::factory()->create(['project_id' => $project->id]);
        $principal = User::factory()->create();
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $principal->id,
            'type' => 'principal',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $alternate = $this->createUserWithRoles('monitoring');
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $alternate->id,
            'type' => 'alternate',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->actingAs($alternate)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertSessionHasErrors(['message']);

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $stage->fresh()->status);
    }

    public function test_advances_monitoring_stage_when_user_is_principal_supervisor(): void
    {
        $project = Project::factory()->create();
        $stage = $this->activateStage($project, ProjectStageSlug::MONITORAMENTO);

        $opening = Opening::factory()->create(['project_id' => $project->id]);
        $principal = $this->createUserWithRoles('monitoring');
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $principal->id,
            'type' => 'principal',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->actingAs($principal)
            ->patch(route('projects.stages.advance', [$project, $stage]))
            ->assertRedirect(route('notices.projects', [
                'notice' => $project->notice_id,
                'phase' => ProjectStageSlug::MONITORAMENTO->value,
            ]))
            ->assertSessionHas('success', 'Processo tramitado com sucesso!');

        $this->assertEquals(ProjectStageStatus::APROVADO, $stage->fresh()->status);
    }

    public function test_return_sends_stage_back_to_previous_one(): void
    {
        Role::firstOrCreate(['name' => 'fomentation', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'coord_fomentation', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $project = Project::factory()->create();
        $stage = $this->activateStage($project, ProjectStageSlug::FORMALIZACAO);
        $user = $this->createUserWithRoles('legal_analysis');

        $this->actingAs($user)
            ->post(route('projects.stages.return', [$project, $stage]), [
                'reason' => 'Documentação incompleta, favor revisar.',
            ])
            ->assertRedirect(route('notices.projects', [
                'notice' => $project->notice_id,
                'phase' => ProjectStageSlug::FORMALIZACAO->value,
            ]))
            ->assertSessionHas('success', 'O processo foi devolvido aos responsáveis!');

        $this->assertEquals(ProjectStageStatus::REJEITADO, $stage->fresh()->status);

        $previousStage = $project->stages()
            ->where('slug', ProjectStageSlug::ANALISE_JURIDICA->value)
            ->first();

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $previousStage->status);
    }

    public function test_return_requires_reason(): void
    {
        $project = Project::factory()->create();
        $stage = $this->activateStage($project, ProjectStageSlug::FORMALIZACAO);
        $user = $this->createUserWithRoles('legal_analysis');

        $this->actingAs($user)
            ->post(route('projects.stages.return', [$project, $stage]), [])
            ->assertSessionHasErrors(['reason']);
    }

    public function test_return_shows_error_when_user_lacks_role(): void
    {
        $project = Project::factory()->create();
        $stage = $this->activateStage($project, ProjectStageSlug::FORMALIZACAO);
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.stages.return', [$project, $stage]), [
                'reason' => 'Documentação incompleta, favor revisar.',
            ])
            ->assertSessionHas('error');

        $this->assertEquals(ProjectStageStatus::EM_ANDAMENTO, $stage->fresh()->status);
    }

    public function test_return_shows_error_when_there_is_no_previous_stage(): void
    {
        $project = Project::factory()->create();
        $stage = $project->stages()
            ->where('slug', ProjectStageSlug::ABERTURA->value)
            ->firstOrFail();
        $user = $this->createUserWithRoles('fomentation');

        $this->actingAs($user)
            ->post(route('projects.stages.return', [$project, $stage]), [
                'reason' => 'Tentativa de devolução inválida.',
            ])
            ->assertSessionHas('error', 'Não há etapa anterior para devolução.');
    }

    // — requestNextInstallment —

    public function test_request_next_installment_returns_error_when_single_installment(): void
    {
        $project = Project::factory()
            ->for(Notice::factory()->state(['installments' => 1]))
            ->create();

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('projects.stages.request-next-installment', $project))
            ->assertSessionHasErrors(['message']);
    }

    public function test_request_next_installment_returns_error_when_monitoring_not_active(): void
    {
        $project = Project::factory()
            ->for(Notice::factory()->state(['installments' => 2]))
            ->create(['current_installment_cycle' => 1]);

        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('projects.stages.request-next-installment', $project))
            ->assertSessionHasErrors(['message']);
    }

    public function test_request_next_installment_returns_error_when_user_is_not_principal_supervisor(): void
    {
        $project = Project::factory()
            ->for(Notice::factory()->state(['installments' => 2]))
            ->create(['current_installment_cycle' => 1]);

        $this->activateStage($project, ProjectStageSlug::MONITORAMENTO);

        $opening = Opening::factory()->create(['project_id' => $project->id]);
        $principal = User::factory()->create();
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $principal->id,
            'type' => 'principal',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $alternate = User::factory()->create();
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $alternate->id,
            'type' => 'alternate',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->actingAs($alternate)
            ->patch(route('projects.stages.request-next-installment', $project))
            ->assertSessionHasErrors(['message']);

        $this->assertEquals(1, $project->fresh()->current_installment_cycle);
    }

    public function test_request_next_installment_redirects_on_success(): void
    {
        $project = Project::factory()
            ->for(Notice::factory()->state(['installments' => 2]))
            ->create(['current_installment_cycle' => 1]);

        $this->activateStage($project, ProjectStageSlug::MONITORAMENTO);

        $opening = Opening::factory()->create(['project_id' => $project->id]);
        $principal = User::factory()->create();
        OpeningSupervisor::create([
            'opening_id' => $opening->id,
            'user_id' => $principal->id,
            'type' => 'principal',
            'is_active' => true,
            'assigned_at' => now(),
        ]);

        $this->actingAs($principal)
            ->patch(route('projects.stages.request-next-installment', $project))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEquals(2, $project->fresh()->current_installment_cycle);
    }
}
