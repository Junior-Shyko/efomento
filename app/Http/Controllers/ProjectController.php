<?php

namespace App\Http\Controllers;

use App\Enums\AccountType;
use App\Enums\AgentStatus;
use App\Enums\CgeAtendeStatus;
use App\Enums\DeliberationType;
use App\Enums\DocumentType;
use App\Enums\InstrumentType;
use App\Enums\MonitoringReportRequestDeadline;
use App\Enums\OpeningStatus;
use App\Enums\ProjectStageSlug;
use App\Enums\ProjectStageStatus;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Http\Resources\DocumentResource;
use App\Http\Resources\ProjectResource;
use App\Models\Notice;
use App\Models\Project;
use App\Models\ProjectStage;
use App\Models\User;
use App\Services\Documents\DocumentPlaceholderResolver;
use App\Services\ProjectDocumentService;
use App\Services\ProjectSupervisorService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ProjectController extends Controller
{
    public function __construct(
        private readonly DocumentPlaceholderResolver $placeholderResolver,
    ) {}

    public function index(Request $request, Notice $notice)
    {
        $projectsQuery = $notice->projects()
            ->with([
                'agent',
                'agent.latestSnapshot',
                'category',
                'opening',
                'opening.supervisors',
                'opening.principalSupervisor.user',
                'documents',
                'documents.project.agent.latestSnapshot',
                'documents.project.notice',
                'documents.project.notice.budgetAllocations',
                'documents.project.opening.principalSupervisor.user',
                'documents.project.budgets.installments.budgetAllocation',
                'currentStage',
                'monitoring',
                'budgets',
                'budgets.installments',
                'budgets.installments.budgetAllocation',
            ])
            ->search($request->search);

        $phaseCountQuery = $notice->projects()
            ->search($request->search);

        $projectsQuery->filterPhase($request->phase);

        $projects = $projectsQuery->get();
        $projects->flatMap->documents
            ->each(fn ($document) => $this->placeholderResolver->prepare($document));

        $noticeDocuments = $notice->documents()
            ->whereNull('project_id')
            ->whereIn('type', [DocumentType::PI, DocumentType::JR])
            ->with(['images', 'notice'])
            ->get()
            ->each(fn ($document) => $this->placeholderResolver->prepare($document));

        return Inertia::render('Projects', [
            'notice' => $notice,

            'projects' => ProjectResource::collection(
                $projects
            )->resolve(),

            'noticeDocuments' => DocumentResource::collection($noticeDocuments)->resolve(),

            'filters' => $request->only([
                'phase',
                'search',
            ]),

            'instrumentTypes' => InstrumentType::values(),

            'monitoringReportRequestDeadlines' => MonitoringReportRequestDeadline::options(),

            'phases' => collect(ProjectStageSlug::cases())
                ->reject(function ($stage) {
                    return $stage === ProjectStageSlug::PRESTACAO_DE_CONTAS;
                })
                ->map(function ($stage) use ($phaseCountQuery) {
                    return [
                        'value' => $stage->value,

                        'title' => $stage->label(),

                        'total' => (clone $phaseCountQuery)
                            ->whereHas('stages', function ($q) use ($stage) {
                                $q->where('slug', $stage)
                                    ->where(
                                        'status',
                                        ProjectStageStatus::EM_ANDAMENTO
                                    );
                            })
                            ->count(),
                    ];
                }),

            'supervisorsAvailable' => User::role(Role::monitoringRoles())
                ->select('id', 'name')
                ->get(),

            'monitoringReportsCount' => $notice->projects()->whereHas('monitoring')->count(),

            'hasBudgetAllocations' => $notice->budgetAllocations()->exists(),
        ]);
    }

    public function projectDetail(Request $request, Notice $notice, Project $project)
    {
        $project->load([
            'notice',
            'notice.budgetAllocations',
            'agent',
            'category',
            'opening',
            'opening.supervisors' => function ($q) {
                $q->whereNull('removed_at')
                    ->orderByRaw("CASE type WHEN 'principal' THEN 0 WHEN 'alternate' THEN 1 ELSE 2 END");
            },
            'opening.supervisors.user',
            'documents',
            'documents.images',
            'budgets',
            'budgets.installments',
            'budgets.installments.budgetAllocation',
            'monitoring',
            'formalizations',
            'formalizations.files',
            'agent.latestSnapshot',
            'monitoringSnapshot',
            'stages',
        ]);

        $project->documents->each(function ($document) use ($project) {
            $document->setRelation('project', $project);
            $this->placeholderResolver->prepare($document);
        });
        $availableSupervisors = User::role(Role::monitoringRoles())
            ->select('id', 'name', 'registration_number')
            ->get();

        $usersAvailableForFormalization = User::role(Role::formalizationRoles())
            ->select('id', 'name', 'registration_number')
            ->get();

        $currentStage = $project->currentStage;

        return Inertia::render('ProjectDetails', [
            'project' => (new ProjectResource($project))->resolve(),
            'supervisorsAvailable' => $availableSupervisors,
            'usersAvailableForFormalization' => $usersAvailableForFormalization,
            'agentStatus' => AgentStatus::options(),
            'accountType' => AccountType::options(),
            'reportStatus' => ReportStatus::options(),
            'deliberation' => DeliberationType::options(),
            'cgeAtendeStatus' => CgeAtendeStatus::options(),
            'openingStatus' => OpeningStatus::options(),
            'currentStage' => $currentStage,
            'canReturn' => $this->userCanActOnStage($currentStage),
            'canAdvance' => $this->userCanActOnStage($currentStage),
            'initialTab' => $request->get('tab', 'opening'),
        ]);
    }

    public function assignProjectSupervisor(Request $request, ProjectSupervisorService $service)
    {
        $data = $request->validate([
            'selected_projects' => 'required|array',
            'selected_projects.*' => 'exists:projects,id',

            'selected_supervisors' => 'required|array',
            'selected_supervisors.*' => 'exists:users,id',
        ]);

        $service->assign(
            $data['selected_projects'],
            $data['selected_supervisors']
        );

        return back()->with('success', 'Fiscais atribuídos com sucesso!');
    }

    public function createDocument(Request $request, ProjectDocumentService $service)
    {
        $data = $request->validate([
            'type' => 'required|in:ci,tc,pj,et,pi,pf,do,dp,jr',

            'notice_id' => 'required_if:type,pi,jr|nullable|exists:notices,id',

            'selected_projects' => 'required_unless:type,pi,jr|array|min:1',
            'selected_projects.*' => 'exists:projects,id',

            'content' => 'required|string',

            'header_images' => 'nullable|array',
            'header_images.*.id' => 'nullable|integer',
            'header_images.*.file' => 'nullable|image',
            'header_images.*._delete' => 'nullable|in:1',

            'footer_images' => 'nullable|array',
            'footer_images.*.id' => 'nullable|integer',
            'footer_images.*.file' => 'nullable|image',
            'footer_images.*._delete' => 'nullable|in:1',

            'header_layout' => 'nullable|in:none,three,full',
            'footer_layout' => 'nullable|in:none,three,full',
        ]);

        $type = DocumentType::from($data['type']);

        if ($type->isBudgetOpinion()) {
            abort_unless($request->user()->hasAnyRole(Role::budgetRoles()), 403);
        }

        if ($type->isJuridicalReference()) {
            abort_unless($request->user()->hasAnyRole(Role::legalAnalysisRoles()), 403);
        }

        if ($type->isNoticeLevel()) {
            $service->createNoticeDocument(
                notice: Notice::findOrFail($data['notice_id']),
                content: $data['content'],
                headerImages: $data['header_images'] ?? [],
                footerImages: $data['footer_images'] ?? [],
                type: $type,
                headerLayout: $data['header_layout'] ?? 'none',
                footerLayout: $data['footer_layout'] ?? 'none',
            );
        } else {
            $service->createDocument(
                selectedProjects: $data['selected_projects'],
                content: $data['content'],
                headerImages: $data['header_images'] ?? [],
                footerImages: $data['footer_images'] ?? [],
                type: $type,
                headerLayout: $data['header_layout'] ?? 'none',
                footerLayout: $data['footer_layout'] ?? 'none',
            );
        }

        return back()->with(
            'success',
            'Documento criado com sucesso! Você pode editá-lo ou baixá-lo na seção de documentos.'
        );
    }

    private function userCanActOnStage(?ProjectStage $currentStage): bool
    {
        return $currentStage
            ? auth()->user()->hasAnyRole($currentStage->responsible_sector)
            : false;
    }
}
