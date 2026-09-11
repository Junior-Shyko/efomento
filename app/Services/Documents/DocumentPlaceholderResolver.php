<?php

namespace App\Services\Documents;

use App\Enums\InstrumentType;
use App\Models\BudgetAllocation;
use App\Models\Document;
use App\Models\Formalization;
use App\Models\Notice;
use App\Models\Project;
use App\Services\BudgetAllocationResolver;
use App\Services\InstrumentSequenceService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class DocumentPlaceholderResolver
{
    public const RELATIONS = [
        'notice.budgetAllocations',
        'project.agent.latestSnapshot',
        'project.notice.budgetAllocations',
        'project.opening.principalSupervisor.user',
        'project.budgets.installments',
        'project.category',
        'project.budgets.installments.budgetAllocation',
        'project.formalizations',
    ];

    public function __construct(
        private readonly BudgetAllocationResolver $budgetAllocationResolver,
        private readonly InstrumentSequenceService $sequenceService,
    ) {}

    public function prepare(Document $document): Document
    {
        $document->resolvedBody = $this->resolve($document);

        return $document;
    }

    public function resolve(Document $document): string
    {
        $document->loadMissing(self::RELATIONS);

        $snapshot = $document->project?->agent?->latestSnapshot;
        $opening = $document->project?->opening;
        $supervisor = $opening?->principalSupervisor?->user;
        $notice = $document->project?->notice ?? $document->notice;
        $currentInstallment = $document->project?->budgets?->installments
            ?->firstWhere('installment_number', $document->project?->current_installment_cycle);
        $body = (string) $document->body;
        // Budget-opinion content uses the current installment, while notice-level documents
        // fall back to the notice's latest allocation for every allocation placeholder.

        $termNumber = $this->resolveTermNumber($document, $body);

        $budgetAllocation = str_contains($body, '[budget_allocation_data]') || ! $document->project
            ? $this->budgetAllocationResolver->resolveForBudgetOpinion($document->project, $notice)
            : $this->budgetAllocationResolver->resolve($document->project);

        $replacements = [
            '[notice_name]' => $notice?->name ?? '',
            '[nup_mother]' => $notice?->nup ?? '',
            '[project_nup]' => $opening?->opening_nup ?? '',
            '[agent_name]' => $document->project?->agent?->name ?? '',
            '[agent_cpf]' => $snapshot?->cpf_cnpj ?? '',
            '[agent_address]' => $snapshot
                ? "{$snapshot->street}, {$snapshot->number} - {$snapshot->neighborhood} - {$snapshot->city}/{$snapshot->state}"
                : '',
            '[agent_email]' => $snapshot?->email ?? '',
            '[agent_phone]' => $snapshot?->phone ?? '',
            '[finality]' => $notice?->instrument_type ?? '',
            '[fiscal_matricula]' => $supervisor?->registration_number ?? '',
            '[fiscal_name]' => $supervisor?->name ?? '',
            '[project_name]' => $document->project?->title_project ?? '',
            '[allocation_code]' => $budgetAllocation?->allocation_code ?? '',
            '[allocation_number]' => $budgetAllocation?->allocation_number ?? '',
            '[notice_installment_number]' => $currentInstallment?->notice_installment_number ?? '',
            '[bank]' => $opening?->bank ?? '',
            '[account_type]' => $opening?->account_type?->label() ?? '',
            '[branch]' => $opening?->branch ?? '',
            '[account]' => $opening?->account ?? '',
            '[budget_allocation_nup]' => $notice?->budget_allocation_nup ?? '',
            '[creditor_registration_nup]' => $notice?->creditor_registration_nup ?? '',
            '[project_category]' => $document->project?->category?->name ?? '',
            '[term_number]' => $termNumber,
        ];

        $body = str_replace(array_keys($replacements), array_values($replacements), $body);

        $body = $this->replaceBudgetAllocationDataWithAllocation($body, $budgetAllocation);

        return $this->replaceBudgetAllocationsByRegionTable($body, $notice);
    }

    public function replaceBudgetAllocationData(string $content, ?Project $project, ?Notice $notice = null): string
    {
        if (! str_contains($content, '[budget_allocation_data]')) {
            return $content;
        }

        return $this->replaceBudgetAllocationDataWithAllocation(
            $content,
            $this->budgetAllocationResolver->resolveForBudgetOpinion($project, $notice),
        );
    }

    public function replaceBudgetAllocationsByRegionTable(string $content, ?Notice $notice): string
    {
        $placeholder = '[budget_allocations_by_region_table]';

        if (! str_contains($content, $placeholder)) {
            return $content;
        }

        if (! $notice) {
            return str_replace($placeholder, '', $content);
        }

        $notice->loadMissing('budgetAllocations');

        $allocations = $notice->budgetAllocations
            ->sortBy([
                ['region_code', 'asc'],
                ['id', 'asc'],
            ])
            ->filter(fn (BudgetAllocation $allocation) => filled($allocation->planning_macroregion)
                && (filled($allocation->allocation_code) || filled($allocation->allocation_number)));

        if ($allocations->isEmpty()) {
            return str_replace($placeholder, '', $content);
        }

        $rows = $allocations
            ->map(function (BudgetAllocation $allocation) {
                $macroregion = $this->macroregionLabel($allocation);
                $budgetAllocation = collect([
                    $allocation->allocation_code,
                    $allocation->allocation_number,
                ])->filter(fn ($value) => filled($value))
                    ->map(fn ($value) => e(trim((string) $value)))
                    ->implode(' - ');

                return '<tr>'
                    .'<td style="border: 1px solid #9ca3af; padding: 5px 7px; vertical-align: top;">'.e($macroregion).'</td>'
                    .'<td style="border: 1px solid #9ca3af; padding: 5px 7px; vertical-align: top;">'.$budgetAllocation.'</td>'
                    .'</tr>';
            })
            ->implode('');

        $table = '<table style="width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 10px; text-align: left;">'
            .'<thead><tr>'
            .'<th style="width: 34%; border: 1px solid #9ca3af; padding: 6px 7px; background-color: #e6f1e3; text-align: center;">Macrorregião de Planejamento</th>'
            .'<th style="width: 66%; border: 1px solid #9ca3af; padding: 6px 7px; background-color: #e6f1e3; text-align: center;">Dotações</th>'
            .'</tr></thead><tbody>'.$rows.'</tbody></table>';

        return str_replace($placeholder, $table, $content);
    }

    private function replaceBudgetAllocationDataWithAllocation(
        string $content,
        ?BudgetAllocation $allocation,
    ): string {
        return str_replace(
            '[budget_allocation_data]',
            $allocation ? $this->budgetAllocationBlock($allocation) : '',
            $content,
        );
    }

    private function budgetAllocationBlock(BudgetAllocation $allocation): string
    {
        $fields = [
            'Gestão/Unidade' => $allocation->management_unit,
            'Programa de Trabalho' => $allocation->work_program,
            'Objetivo' => $allocation->objective,
            'Entrega' => $allocation->deliverable,
            'Função' => $allocation->budget_function,
            'Subfunção' => $allocation->budget_subfunction,
            'Ação' => $allocation->project_activity,
            'Elemento de Despesa' => $allocation->expense_element,
            'Fonte de Recursos' => $allocation->funding_source,
            'MAPP' => $allocation->mapp,
            'Projeto Finalístico' => $allocation->finalistic_project,
        ];

        $lines = collect($fields)
            ->map(function ($value, string $label) {
                $escapedValue = nl2br(e((string) ($value ?? '')), false);

                return '<span style="display: block;"><strong>'.e($label).':</strong> '.$escapedValue.'</span>';
            })
            ->implode('');

        return '<span style="display: block; line-height: 1.35; text-align: left;">'.$lines.'</span>';
    }

    private function macroregionLabel(BudgetAllocation $allocation): string
    {
        $code = trim((string) $allocation->region_code);
        $macroregion = trim((string) $allocation->planning_macroregion);

        if ($code === '' || preg_match('/^'.preg_quote($code, '/').'\s*[-–—]/u', $macroregion)) {
            return $macroregion;
        }

        return $code.' – '.$macroregion;
    }

    private function resolveTermNumber(Document $document, string $body): string
    {
        $project = $document->project;

        if (! $project) {
            return '';
        }

        // Valida se o documento requer a geração do número, seja pela fase ou pela tag
        $phase = $document->phase instanceof \BackedEnum ? $document->phase->value : $document->phase;
        $isFormalizationPhase = $phase === 'formalization';
        $hasPlaceholder = str_contains($body, '[term_number]');

        if (! $isFormalizationPhase && ! $hasPlaceholder) {
            $formalization = $project->formalizations instanceof Collection
                ? $project->formalizations->first()
                : $project->formalizations;

            return $formalization?->term_number ?? '';
        }

        // Transação atômica bloqueando o projeto para evitar condição de corrida
        return DB::transaction(function () use ($project) {
            $lockedProject = Project::where('id', $project->id)->lockForUpdate()->first();

            // Consulta direto no banco respeitando o lock
            $formalization = $lockedProject->formalizations()->first();

            if ($formalization && ! empty($formalization->term_number)) {
                return $formalization->term_number;
            }

            $notice = $lockedProject->notice;
            $instrumentTypeString = $notice?->instrument_type;

            $instrumentType = $instrumentTypeString
                ? InstrumentType::tryFrom($instrumentTypeString)
                : InstrumentType::EXECUCAO_CULTURAL;

            if (! $formalization) {
                $formalization = Formalization::create([
                    'project_id' => $lockedProject->id,
                ]);
            }

            $termNumber = $this->sequenceService->generateNextTermNumber($instrumentType);

            $formalization->update([
                'term_number' => $termNumber,
            ]);

            return $termNumber;
        });
    }
}
