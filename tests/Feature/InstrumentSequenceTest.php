<?php

namespace Tests\Feature;

use App\Enums\InstrumentType;
use App\Models\Document;
use App\Models\Notice;
use App\Models\Project;
use App\Services\Documents\DocumentPlaceholderResolver;
use App\Services\InstrumentSequenceService;
use App\Services\NoticeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InstrumentSequenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_generates_first_term_number_starting_at_one(): void
    {
        $this->travelTo('2026-01-01');

        $service = app(InstrumentSequenceService::class);

        $termNumber = $service->generateNextTermNumber(InstrumentType::EXECUCAO_CULTURAL);

        $this->assertEquals('1/2026', $termNumber);
    }

    public function test_locks_projects_and_resets_term_number_when_instrument_type_changes(): void
    {
        $this->travelTo('2026-01-01');

        $notice = Notice::factory()->create([
            'instrument_type' => InstrumentType::EXECUCAO_CULTURAL->value,
        ]);

        $project = Project::factory()->create([
            'notice_id' => $notice->id,
        ]);

        $document = Document::factory()->create([
            'project_id' => $project->id,
            'notice_id' => $notice->id,
            'phase' => 'formalization',
            'body' => 'Termo Nº [term_number].',
        ]);

        $resolver = app(DocumentPlaceholderResolver::class);
        $noticeService = app(NoticeService::class);

        $firstResolved = $resolver->resolve($document);
        $this->assertStringContainsString('1/2026', $firstResolved);
        $this->assertEquals('1/2026', $project->formalizations()->first()->term_number);

        $noticeService->update($notice, [
            'instrument_type' => InstrumentType::FOMENTO->value,
        ]);

        $this->assertNull($project->formalizations()->first()->term_number);

        $secondResolved = $resolver->resolve($document->fresh());
        $this->assertStringContainsString('1/2026', $secondResolved);
        $this->assertEquals('1/2026', $project->formalizations()->first()->fresh()->term_number);
    }

    public function test_resets_term_number_when_stale_notice_instance_submits_reverted_instrument_type(): void
    {
        $this->travelTo('2026-01-01');

        $notice = Notice::factory()->create([
            'instrument_type' => InstrumentType::EXECUCAO_CULTURAL->value,
        ]);

        $project = Project::factory()->create([
            'notice_id' => $notice->id,
        ]);

        $document = Document::factory()->create([
            'project_id' => $project->id,
            'notice_id' => $notice->id,
            'phase' => 'formalization',
            'body' => 'Termo Nº [term_number].',
        ]);

        $resolver = app(DocumentPlaceholderResolver::class);
        $noticeService = app(NoticeService::class);

        $resolver->resolve($document);

        $staleNotice = $notice->fresh();

        $noticeService->update($notice->fresh(), [
            'instrument_type' => InstrumentType::FOMENTO->value,
        ]);

        $resolver->resolve($document->fresh());
        $this->assertEquals('1/2026', $project->formalizations()->first()->fresh()->term_number);

        $noticeService->update($staleNotice, [
            'instrument_type' => InstrumentType::EXECUCAO_CULTURAL->value,
        ]);

        $this->assertNull($project->formalizations()->first()->fresh()->term_number);
    }
}
