<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Enums\Role;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Document;
use App\Models\Notice;
use App\Models\User;
use App\Services\ProjectDocumentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Spatie\Permission\Models\Role as SpatieRole;
use Tests\TestCase;

class JuridicalReferenceDocumentTest extends TestCase
{
    use RefreshDatabase;

    public function test_legal_user_can_create_juridical_reference_for_the_notice_without_a_project(): void
    {
        $user = $this->userWithRole(Role::LEGAL_ANALYSIS);
        $notice = Notice::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.create-document'), [
                'type' => 'jr',
                'notice_id' => $notice->id,
                'content' => 'Conteúdo do parecer jurídico referencial.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseHas('documents', [
            'project_id' => null,
            'notice_id' => $notice->id,
            'type' => 'jr',
            'phase' => 'juridical',
            'created_by' => $user->id,
            'body' => 'Conteúdo do parecer jurídico referencial.',
        ]);
    }

    public function test_creating_juridical_reference_again_updates_existing_document(): void
    {
        $user = $this->userWithRole(Role::LEGAL_ANALYSIS);
        $notice = Notice::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.create-document'), [
                'type' => 'jr',
                'notice_id' => $notice->id,
                'content' => 'Primeira versão do parecer.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->actingAs($user)
            ->post(route('projects.create-document'), [
                'type' => 'jr',
                'notice_id' => $notice->id,
                'content' => 'Segunda versão do parecer atualizada.',
            ])
            ->assertRedirect()
            ->assertSessionHas('success');

        $this->assertDatabaseCount('documents', 1);
        $this->assertDatabaseHas('documents', [
            'project_id' => null,
            'notice_id' => $notice->id,
            'type' => 'jr',
            'phase' => 'juridical',
            'body' => 'Segunda versão do parecer atualizada.',
        ]);
    }

    public function test_legal_user_can_create_juridical_reference_without_a_project_through_api(): void
    {
        $user = $this->userWithRole(Role::LEGAL_ANALYSIS);
        $notice = Notice::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/documents', [
                'type' => 'jr',
                'phase' => 'juridical',
                'notice_id' => $notice->id,
                'body' => 'Conteúdo do parecer jurídico referencial.',
            ])
            ->assertCreated()
            ->assertJsonPath('data.project_id', null);

        $this->assertDatabaseHas('documents', [
            'notice_id' => $notice->id,
            'project_id' => null,
            'type' => 'jr',
            'phase' => 'juridical',
        ]);
    }

    public function test_api_rejects_a_duplicate_notice_level_juridical_reference(): void
    {
        $user = $this->userWithRole(Role::LEGAL_ANALYSIS);
        $notice = Notice::factory()->create();
        $payload = [
            'type' => 'jr',
            'phase' => 'juridical',
            'notice_id' => $notice->id,
            'body' => 'Conteúdo do parecer jurídico referencial.',
        ];

        $this->actingAs($user)->postJson('/api/documents', $payload)->assertCreated();

        $this->actingAs($user)
            ->postJson('/api/documents', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('notice_id');

        $this->assertDatabaseCount('documents', 1);
    }

    public function test_guest_cannot_delete_a_juridical_reference(): void
    {
        $document = Document::factory()->create([
            'project_id' => null,
            'type' => 'jr',
            'phase' => 'juridical',
        ]);

        $this->deleteJson("/api/documents/{$document->id}")->assertUnauthorized();

        $this->assertDatabaseHas('documents', [
            'id' => $document->id,
            'deleted_at' => null,
        ]);
    }

    #[DataProvider('legalAnalysisRoles')]
    public function test_each_legal_role_can_create_juridical_reference(string $role): void
    {
        $user = $this->userWithRole(Role::from($role));
        $notice = Notice::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.create-document'), [
                'type' => 'jr',
                'notice_id' => $notice->id,
                'content' => 'Conteúdo do JR.',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();
    }

    public function test_user_without_legal_role_cannot_create_juridical_reference(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create();

        $this->actingAs($user)
            ->post(route('projects.create-document'), [
                'type' => 'jr',
                'notice_id' => $notice->id,
                'content' => 'Conteúdo sem permissão.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('documents', [
            'type' => 'jr',
        ]);
    }

    public function test_user_without_legal_role_cannot_create_juridical_reference_through_api(): void
    {
        $user = User::factory()->create();
        $notice = Notice::factory()->create();

        $this->actingAs($user)
            ->postJson('/api/documents', [
                'type' => 'jr',
                'phase' => 'juridical',
                'notice_id' => $notice->id,
                'body' => 'Conteúdo sem permissão.',
            ])
            ->assertForbidden();

        $this->assertDatabaseMissing('documents', [
            'type' => 'jr',
        ]);
    }

    public function test_create_notice_document_service_throws_for_non_notice_level_types(): void
    {
        $service = app(ProjectDocumentService::class);
        $notice = Notice::factory()->create();

        $this->expectException(BusinessRuleException::class);
        $this->expectExceptionMessage('Este tipo de documento não pode ser vinculado diretamente ao edital.');

        $service->createNoticeDocument(
            notice: $notice,
            type: DocumentType::PJ,
            content: 'Conteúdo qualquer'
        );
    }

    public static function legalAnalysisRoles(): array
    {
        return [
            'legal_analysis' => [Role::LEGAL_ANALYSIS->value],
            'coord_legal' => [Role::COORD_LEGAL->value],
            'coord_financial' => [Role::COORD_FINANCIAL->value],
            'super_admin' => [Role::SUPER_ADMIN->value],
        ];
    }

    private function userWithRole(Role $role): User
    {
        SpatieRole::firstOrCreate([
            'name' => $role->value,
            'guard_name' => 'web',
        ]);

        $user = User::factory()->create();
        $user->assignRole($role->value);

        return $user;
    }
}
