<?php

namespace App\Services;

use App\Enums\AccountType;
use App\Enums\AgentStatus;
use App\Enums\DisabilityType;
use App\Enums\ProfileSnapshotSource;
use App\Enums\ReportStatus;
use App\Enums\Role;
use App\Jobs\LoadSpreadsheetProjectFilesJob;
use App\Jobs\SyncOpeningRegistrationDataJob;
use App\Models\Formalization;
use App\Models\Notice;
use App\Models\Opening;
use App\Models\OpeningSupervisor;
use App\Models\Project;
use App\Models\User;
use App\Support\DocumentNumber;
use App\Support\Import;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class SpreadsheetImportService
{
    public function __construct(
        private readonly ProfileSnapshotService $snapshotService,
        private readonly CategoryService $categoryService,
        private readonly AgentService $agentService,
        private readonly UserService $userService,
    ) {}

    /**
     * Processa uma linha da planilha e persiste Category, Agent e Project.
     * Retorna o Project criado, ou null se a linha não tiver os dados mínimos.
     */
    public function processRow(
        array $row,
        bool $withFiles,
        int $userId,
        ?int $fallbackNoticeId = null,
        bool $withRegistrationData = false,
    ): ?Project {
        return DB::transaction(function () use ($row, $withFiles, $userId, $fallbackNoticeId, $withRegistrationData) {
            $registrationUrl = trim((string) ($row['LINK FICHA DE INSCRIÇÃO'] ?? ''));
            $registrationId = $this->extractRegistrationId($registrationUrl);

            if (! $registrationId) {
                return null;
            }

            $agent = $this->agentService->updateOrCreatedByDocument(
                document: $row['CPF / CNPJ DO PROPONENTE'] ?? null,
                name: $row['NOME COMPLETO / RAZÃO SOCIAL DO PROPONENTE'] ?? null,
            );

            if (! $agent) {
                Log::warning('spreadsheet.import.agent_cpf_missing', [
                    'registration_id' => $registrationId,
                    'cpf_column' => $row['CPF / CNPJ DO PROPONENTE'] ?? null,
                ]);

                return null;
            }

            $noticeExternalId = trim((string) ($row['Nº DA INSCRIÇÃO NO MAPA DA CULTURA'] ?? ''));

            $notice = Notice::where('external_id', $noticeExternalId)->first()
                ?? ($fallbackNoticeId ? Notice::find($fallbackNoticeId) : null);

            if (! $notice) {
                Log::warning('spreadsheet.import.notice_missing', [
                    'notice_external_id' => $noticeExternalId,
                    'registration_id' => $registrationId,
                ]);

                return null;
            }

            $category = $this->categoryService->findOrCreateProject(
                $row['CATEGORIA'] ?? null
            );

            $snapshotData = $this->buildSnapshotData($row);

            $this->snapshotService->recordIfChanged(
                $agent,
                $snapshotData,
                ProfileSnapshotSource::AGENT_UPDATE,
            );

            $project = Project::firstOrCreate(
                ['registration_id' => $registrationId],
                [
                    'number' => 'on-'.$registrationId,
                    'category_id' => $category?->id,
                    'agent_id' => $agent->id,
                    'notice_id' => $notice->id,
                    'title_project' => trim((string) ($row['TITULO DO PROJETO'] ?? '')) ?: null,
                ]
            );

            $this->snapshotService->recordIfChanged(
                $project,
                $snapshotData,
                ProfileSnapshotSource::PROJECT_REGISTRATION,
            );

            $supervisor = $this->resolveSupervisor($row);

            $this->resolveOpening($row, $project->id, $userId, $supervisor);
            $this->resolveFormalization($row, $project->id, $userId);

            if ($withFiles) {
                LoadSpreadsheetProjectFilesJob::dispatch(
                    projectId: $project->id,
                    registrationId: (int) $registrationId,
                )
                    ->afterCommit()
                    ->onQueue('details');
            }

            if ($withRegistrationData) {
                SyncOpeningRegistrationDataJob::dispatch(
                    projectId: $project->id,
                    registrationId: (int) $registrationId,
                    userId: $userId,
                )
                    ->afterCommit()
                    ->onQueue('details');
            }

            return $project;
        }, 3);
    }

    private function buildSnapshotData(array $row): array
    {
        return [
            'cpf_cnpj' => DocumentNumber::normalize($row['CPF / CNPJ DO PROPONENTE'] ?? null) ?: null,
            'gender' => trim((string) ($row['GÊNERO DA PESSOA FÍSICA'] ?? '')) ?: null,
            'has_disability' => $this->mapDisability(trim((string) ($row['POSSUI DEFICIENCIA (PESSOA FÍSICA)'] ?? ''))),
            'email' => trim((string) ($row['E-MAIL PRINCIPAL DO PROPONENTE'] ?? '')) ?: null,
            'secondary_email' => trim((string) ($row['E-MAIL SECUNDÁRIO DO PROPONENTE'] ?? '')) ?: null,
            'phone' => trim((string) ($row['TELEFONE PRINCIPAL DO PROPONENTE'] ?? '')) ?: null,
            'birth_date' => $this->parseDate(trim((string) ($row['DATA DE NASCIMENTO DA PESSOA FÍSICA'] ?? ''))),
            'street' => trim((string) ($row['ENDEREÇO COMPLETO DO PROPONENTE'] ?? '')) ?: null,
            'city' => trim((string) ($row['CIDADE DO PROPONENTE'] ?? '')) ?: null,
        ];
    }

    private function resolveOpening(array $row, int $projectId, int $userId, ?User $supervisor): Opening
    {
        $opening = Opening::firstOrNew(['project_id' => $projectId]);

        $attributes = [
            'opening_nup' => trim((string) (Import::normalizeNup($row['N° DO PROCESSO (NUP)']) ?? '')),
            'opening_date' => $this->parseDate(trim((string) ($row['DATA ABERTURA DE PROCESSO'] ?? ''))),
            'agent_status' => $this->mapAgentStatus(trim((string) ($row['STATUS'] ?? ''))),
            'opened_by' => trim((string) ($row['RESPONSÁVEL POR ABRIR PROCESSO'] ?? '')),
            'bank' => trim((string) ($row['BANCO'] ?? '')),
            'account_type' => $this->mapAccountType(trim((string) ($row['TIPO DE CONTA'] ?? ''))),
            'branch' => trim((string) ($row['AGÊNCIA'] ?? '')),
            'account' => trim((string) ($row['CONTA'] ?? '')),
            'certificate_date' => $this->parseDate(trim((string) ($row['DATA DE CERTIDÃO GERADA'] ?? ''))),
            'started_at' => $this->parseDate(trim((string) ($row['DATA ABERTURA DE PROCESSO'] ?? ''))),
            'user_id' => $userId,
            'supervisor_id' => $supervisor?->id,
        ];

        if (! $opening->exists) {
            $attributes['is_draft'] = true;
        }

        $opening->fill($attributes);
        $opening->save();

        if ($supervisor) {
            $opening->assignSupervisors([
                ['id' => $supervisor->id, 'type' => OpeningSupervisor::TYPE_PRINCIPAL],
            ], $userId);
        }

        return $opening;
    }

    /**
     * Resolve o usuário Fiscal pela coluna "FISCAL". Prioriza identificadores
     * estáveis (CPF, depois matrícula) para evitar que dois fiscais com o
     * mesmo nome sejam confundidos; "name" é só o último fallback.
     * Cria o usuário (sem acesso ativo além da role monitoring) se não existir,
     * com um e-mail sintético sob domínio reservado/não roteável, já que a
     * planilha não traz e-mail do fiscal.
     */
    private function resolveSupervisor(array $row): ?User
    {
        $name = trim((string) ($row['FISCAL'] ?? ''));

        if ($name === '') {
            return null;
        }

        $cpf = DocumentNumber::normalize($row['CPF FISCAL'] ?? null);
        $registrationNumber = trim((string) ($row['MATRICULA DO FISCAL'] ?? '')) ?: null;

        // Quando a planilha traz um identificador estável, ele é a única fonte de
        // verdade (não cai para "name" mesmo sem achar match) — dois fiscais
        // podem ter o mesmo nome, e reaproveitar por coincidência de nome
        // atribuiria o processo ao fiscal errado.
        $user = match (true) {
            $cpf !== null => User::where('cpf', $cpf)->first(),
            $registrationNumber !== null => User::where('registration_number', $registrationNumber)->first(),
            default => User::where('name', $name)->first(),
        };

        if ($user) {
            return $user;
        }

        return $this->userService->create([
            'name' => $name,
            'email' => Str::slug($name, '.').'.'.Str::random(6).'@fiscal.invalid',
            'password' => Str::password(32),
            'cpf' => $cpf,
            'registration_number' => $registrationNumber,
            'role' => Role::MONITORING->value,
        ]);
    }

    private function resolveFormalization(array $row, int $projectId, int $userId): Formalization
    {
        $reportStatus = $this->mapReportStatus(trim((string) ($row['REGULARIDADE E ADIMPLÊNCIA (E-PARCERIAS) (I)'] ?? '')));
        $certificateDate = $this->parseDate(trim((string) ($row['DATA DE CERTIDÃO GERADA'] ?? '')));

        $record = ['created_by' => $userId];

        if ($reportStatus !== null) {
            $record['report_status'] = $reportStatus;
        }

        if ($certificateDate !== null) {
            $record['eparcerias_certificate_date'] = $certificateDate;
        }

        return Formalization::updateOrCreate(
            ['project_id' => $projectId],
            $record
        );
    }

    private function extractRegistrationId(string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $path = parse_url($url, PHP_URL_PATH);

        if (! is_string($path)) {
            return null;
        }

        $id = basename($path);

        return ctype_digit($id) ? $id : null;
    }

    private function mapAgentStatus(string $value): AgentStatus
    {
        return match ($value) {
            'Habilitado', 'Ativo' => AgentStatus::ATIVO,
            'Suplente' => AgentStatus::SUPLENTE,
            'Em análise' => AgentStatus::EM_ANALISE,
            'Desclassificado' => AgentStatus::DESCLASSIFICADO,
            'Desistente' => AgentStatus::DESISTENTE,
            default => AgentStatus::EM_ANALISE,
        };
    }

    private function mapAccountType(string $value): AccountType
    {
        return str_contains(strtolower($value), 'poupan')
            ? AccountType::POUPANCA
            : AccountType::CORRENTE;
    }

    private function mapDisability(string $value): DisabilityType
    {
        return match ($value) {
            'Auditiva' => DisabilityType::AUDITIVA,
            'Física' => DisabilityType::FISICA_MOTORA,
            'Intelectual' => DisabilityType::INTELECTUAL,
            'Múltipla' => DisabilityType::MULTIPLA,
            'Visual' => DisabilityType::VISUAL,
            default => DisabilityType::NAO,
        };
    }

    private function mapReportStatus(string $value): ?ReportStatus
    {
        return ReportStatus::tryFromLoose($value);
    }

    private function parseDate(string $value): ?Carbon
    {
        $normalized = Import::date($value);

        return $normalized ? Carbon::parse($normalized) : null;
    }
}
