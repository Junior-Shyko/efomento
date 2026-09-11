<?php

namespace App\Services;

use App\Enums\DocumentImagePosition;
use App\Enums\DocumentImageSection;
use App\Enums\DocumentType;
use App\Exceptions\Domain\BusinessRuleException;
use App\Models\Document;
use App\Models\Notice;
use App\Models\Project;
use App\Support\ImageSync;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class ProjectDocumentService
{
    public function __construct(
        private readonly BudgetAllocationResolver $budgetAllocationResolver,
    ) {}

    public function createNoticeDocument(
        Notice $notice,
        DocumentType $type,
        string $content,
        array $headerImages = [],
        array $footerImages = [],
        string $headerLayout = 'none',
        string $footerLayout = 'none',
    ): void {
        if (! $type->isNoticeLevel()) {
            throw new BusinessRuleException('Este tipo de documento não pode ser vinculado diretamente ao edital.');
        }

        DB::transaction(function () use (
            $notice,
            $type,
            $content,
            $headerImages,
            $footerImages,
            $headerLayout,
            $footerLayout
        ) {
            if (empty(trim($content))) {
                throw new BusinessRuleException('O conteúdo do documento não pode ser vazio.');
            }

            Notice::query()
                ->whereKey($notice->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $document = Document::query()
                ->where('notice_id', $notice->id)
                ->whereNull('project_id')
                ->where('type', $type)
                ->where('phase', $type->phase())
                ->with('images')
                ->first();

            $isSync = $document !== null;

            if ($document) {
                $document->update(['body' => $content]);
            } else {
                $document = Document::create([
                    'notice_id' => $notice->id,
                    'project_id' => null,
                    'type' => $type,
                    'phase' => $type->phase(),
                    'body' => $content,
                    'created_by' => auth()->id(),
                ]);
            }

            $this->syncDocumentImages(
                $document,
                $headerImages,
                $footerImages,
                $headerLayout,
                $footerLayout,
                $isSync,
            );
        });
    }

    public function createDocument(
        DocumentType $type,
        array $selectedProjects,
        string $content,
        array $headerImages = [],
        array $footerImages = [],
        string $headerLayout = 'none',
        string $footerLayout = 'none',
    ): void {
        DB::transaction(function () use (
            $type,
            $selectedProjects,
            $content,
            $headerImages,
            $footerImages,
            $headerLayout,
            $footerLayout
        ) {
            if (empty(trim($content))) {
                throw new BusinessRuleException('O conteúdo do documento não pode ser vazio.');
            }

            $hasBudgetResultTable = str_contains($content, '[budget_result_table]');
            $projects = Project::query()
                ->whereIn('id', $selectedProjects)
                ->when(
                    $hasBudgetResultTable,
                    fn ($query) => $query->with([
                        'agent.latestSnapshot',
                        'budgets.installments.budgetAllocation',
                        'notice.budgetAllocations',
                    ])
                )
                ->get();
            $phase = $type->phase();

            if ($projects->isEmpty()) {
                throw new BusinessRuleException('Nenhum projeto selecionado.');
            }

            if ($hasBudgetResultTable) {
                $projects = $this->orderProjectsBySelection($projects, $selectedProjects);
                $content = $this->replaceBudgetResultTable($content, $projects);
            }

            $documents = Document::whereIn('project_id', $projects->pluck('id'))
                ->where('type', $type)
                ->where('phase', $phase)
                ->with('images')
                ->get()
                ->keyBy('project_id');

            foreach ($projects as $project) {

                $document = $documents->get($project->id);

                if ($document) {

                    $document->update([
                        'body' => $content,
                    ]);

                    $this->syncDocumentImages(
                        $document,
                        $headerImages,
                        $footerImages,
                        $headerLayout,
                        $footerLayout,
                        true,
                    );

                    continue;
                }

                $document = Document::create([
                    'notice_id' => $project->notice_id,
                    'project_id' => $project->id,
                    'type' => $type,
                    'phase' => $phase,
                    'body' => $content,
                    'created_by' => auth()->id(),
                ]);

                $this->syncDocumentImages(
                    $document,
                    $headerImages,
                    $footerImages,
                    $headerLayout,
                    $footerLayout,
                    false,
                );
            }
        });
    }

    private function orderProjectsBySelection(Collection $projects, array $selectedProjects): Collection
    {
        $positions = collect($selectedProjects)
            ->values()
            ->flip();

        return $projects
            ->sortBy(fn (Project $project) => $positions->get($project->id, PHP_INT_MAX))
            ->values();
    }

    private function replaceBudgetResultTable(string $content, Collection $projects): string
    {
        $placeholder = '[budget_result_table]';

        $rows = $projects
            ->map(function (Project $project) {
                $budgetAllocation = $this->budgetAllocationResolver->resolveForBudgetOpinion($project);
                $allocationCode = $budgetAllocation?->allocation_code;
                $allocationNumber = $budgetAllocation?->allocation_number;

                $cells = [
                    $project->registration_id,
                    $project->agent?->name,
                    $project->agent?->latestSnapshot?->city,
                    $allocationCode,
                    $allocationNumber,
                ];

                return '<tr>'.collect($cells)
                    ->map(fn ($value) => '<td style="border: 1px solid #9ca3af; padding: 5px 6px; vertical-align: top; word-wrap: break-word;">'.e((string) ($value ?? '')).'</td>')
                    ->implode('').'</tr>';
            })
            ->implode('');

        $table = '<!-- budget_result_table:start -->'
            .'<table data-document-placeholder="budget_result_table" style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 8px; text-align: left;">'
            .'<thead><tr>'
            .'<th style="width: 14%; border: 1px solid #9ca3af; padding: 6px; background-color: #d9d9d9; text-align: center;">CÓDIGO<br>INSCRIÇÃO<br>MAPAS</th>'
            .'<th style="width: 29%; border: 1px solid #9ca3af; padding: 6px; background-color: #d9d9d9; text-align: center;">NOME COMPLETO / RAZÃO SOCIAL<br>DO PROPONENTE</th>'
            .'<th style="width: 15%; border: 1px solid #9ca3af; padding: 6px; background-color: #d9d9d9; text-align: center;">CIDADE<br>PROPONENTE</th>'
            .'<th style="width: 20%; border: 1px solid #9ca3af; padding: 6px; background-color: #d9d9d9; text-align: center;">CÓDIGO DA DOTAÇÃO</th>'
            .'<th style="width: 22%; border: 1px solid #9ca3af; padding: 6px; background-color: #d9d9d9; text-align: center;">DOTAÇÃO<br>ORÇAMENTÁRIA</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>'
            .'<!-- budget_result_table:end -->';

        return str_replace($placeholder, $table, $content);
    }

    private function syncDocumentImages(
        Document $document,
        array $headerImages,
        array $footerImages,
        string $headerLayout,
        string $footerLayout,
        bool $isSync,
    ): void {
        $processedHeader = ImageSync::handle($headerImages, fn () => $document->images);
        $processedFooter = ImageSync::handle($footerImages, fn () => $document->images);

        $existingImages = $isSync
            ? $document->images
                ->groupBy(fn ($img) => $img->section->value)
                ->map(fn ($group) => $group->keyBy('position'))
            : null;

        $this->persistImages(
            $document,
            $processedHeader,
            DocumentImageSection::HEADER,
            $existingImages,
            $headerLayout === 'full'
        );

        $this->persistImages(
            $document,
            $processedFooter,
            DocumentImageSection::FOOTER,
            $existingImages,
            $footerLayout === 'full'
        );
    }

    private function persistImages(
        Document $document,
        array $items,
        DocumentImageSection $section,
        ?Collection $existingGrouped = null,
        bool $isFullWidth = false
    ): void {
        $isSync = $existingGrouped !== null;

        foreach ($items as $index => $item) {
            if (! is_array($item)) {
                continue;
            }

            $position = DocumentImagePosition::fromIndex($index);

            if (! $position) {
                continue;
            }

            $path = $item['path'] ?? null;
            $delete = ! empty($item['_delete']);

            if ($isSync) {
                $existing = $existingGrouped[$section->value][$position->value] ?? null;

                if ($delete) {
                    $existing?->delete();

                    continue;
                }

                if ($path) {
                    if ($existing && $existing->path === $path) {
                        $existing->update(['is_full_width' => $isFullWidth]);

                        continue;
                    }

                    if ($existing) {
                        $existing->delete();
                    }

                    $document->images()->create([
                        'section' => $section,
                        'position' => $position,
                        'path' => $path,
                        'is_full_width' => $isFullWidth,
                    ]);
                }

                continue;
            }

            if (! $path) {
                continue;
            }

            $document->images()->create([
                'section' => $section,
                'position' => $position,
                'path' => $path,
                'is_full_width' => $isFullWidth,
            ]);
        }
    }
}
