<?php

namespace App\Services;

use App\Enums\CgeAtendeStatus;
use App\Enums\DeliberationType;
use App\Enums\ReportStatus;
use App\Exceptions\Integration\ExternalServiceException;
use App\Models\Budget;
use App\Models\Formalization;
use App\Models\Project;
use App\Support\Import;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class GoogleSheetsService
{
    private const GVIZ_ENDPOINT = 'https://docs.google.com/spreadsheets/d/%s/gviz/tq';

    public function __construct(
        private readonly SpreadsheetImportService $importService,
    ) {}

    /**
     * Fetches all rows from a public Google Sheet tab and returns a structured array.
     *
     * @return array{columns: string[], rows: array<int, array<string, mixed>>}
     *
     * @throws ExternalServiceException
     * @throws RuntimeException
     */
    public function fetchSheet(string $spreadsheetId, string $sheetName): array
    {
        $url = sprintf(self::GVIZ_ENDPOINT, $spreadsheetId);

        try {
            $raw = Http::timeout(30)
                ->get($url, ['tqx' => 'out:json', 'sheet' => $sheetName])
                ->throw()
                ->body();
        } catch (ConnectionException|RequestException $e) {
            throw ExternalServiceException::unavailable('Google Sheets', $e);
        }
        $json = $this->stripSecurityPrefix($raw);

        $table = $this->decodeTable($json);

        $columns = $this->extractColumns($table);

        return [
            'columns' => $columns,
            'rows' => $this->extractRows($table, $columns),
        ];
    }

    /**
     * Usando o SpreadsheetImportService::processRow().
     * Retorna o número de linhas gravadas.
     */
    public function importSheet(
        string $spreadsheetId,
        string $sheetName,
        bool $withFiles,
        int $userId,
        ?int $fallbackNoticeId = null,
        bool $withRegistrationData = false,
    ): int {
        ['rows' => $rows] = $this->fetchSheet($spreadsheetId, $sheetName);

        $count = 0;
        foreach ($rows as $row) {
            if ($this->importService->processRow($row, $withFiles, $userId, $fallbackNoticeId, $withRegistrationData) !== null) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Sincroniza a aba de Formalização com o model Formalization.
     * Retorna o número de registros gravados.
     */
    public function syncFormalization(string $spreadsheetId, string $sheetName, int $userId): int
    {
        $config = config('spreadsheet_mappings.formalizacao');
        $columnMap = $config['column_map'];
        $projectLookupColumn = $config['column_for_project_lookup'];

        ['rows' => $rows] = $this->fetchSheet($spreadsheetId, $sheetName);

        $projects = $this->preloadProjectsByNumber($rows, $projectLookupColumn);

        $count = 0;
        foreach ($rows as $row) {
            if (($row['STATUS'] ?? null) === 'Desclassificado' || ($row['STATUS'] ?? null) === 'Desistente') {
                continue;
            }

            $project = $projects->get($row[$projectLookupColumn] ?? null);

            if (! $project) {
                continue;
            }

            $record = ['process_supervisor_id' => $userId, 'created_by' => $userId];
            foreach ($columnMap as $sheetColumn => $modelField) {
                $rawValue = $row[$sheetColumn] ?? null;

                if ($modelField === 'cge_atende_ticket') {
                    $normalized = $this->normalizeCgeAtendeStatus($rawValue, $project->number);

                    if ($this->rejectedInvalidValue($normalized, $rawValue)) {
                        continue;
                    }

                    $record[$modelField] = $normalized;

                    continue;
                }

                if ($modelField === 'report_status') {
                    $normalized = $this->normalizeReportStatus($rawValue, $project->number);

                    if ($this->rejectedInvalidValue($normalized, $rawValue)) {
                        continue;
                    }

                    $record[$modelField] = $normalized;

                    continue;
                }

                if ($modelField === 'deliberation') {
                    $normalized = $this->normalizeDeliberationType($rawValue, $project->number);

                    if ($this->rejectedInvalidValue($normalized, $rawValue)) {
                        continue;
                    }

                    $record[$modelField] = $normalized;

                    continue;
                }

                $record[$modelField] = $rawValue;
            }

            try {
                Formalization::updateOrCreate(['project_id' => $project->id], $record);

                // Sempre sobrescreve (mesmo quando null): um NUP removido/corrigido
                // na planilha precisa refletir em Opening, não ficar com valor antigo.
                $nup = Import::normalizeNup($row[$config['opening_nup_column'] ?? 'N° DO PROCESSO (NUP)'] ?? null);
                $project->opening?->update(['opening_nup' => $nup]);

                $count++;
            } catch (Throwable $e) {
                Log::warning('spreadsheet.import.formalization_sync_failed', [
                    'project_number' => $project->number,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $count;
    }

    private function normalizeReportStatus(mixed $value, ?string $projectNumber): ?string
    {
        $status = ReportStatus::tryFromLoose($value);

        if ($status === null && ! blank($value)) {
            Log::warning('spreadsheet.import.report_status_invalid', [
                'project_number' => $projectNumber,
                'value' => $value,
            ]);
        }

        return $status?->value;
    }

    /**
     * Normaliza o valor bruto da coluna "CHAMADO CGE ATENDE" para o enum CgeAtendeStatus.
     * Valores que não correspondam a nenhum case (números de chamado antigos, texto fora do padrão)
     * são descartados (null) e registrados em log, em vez de interromper a sincronização.
     */
    private function normalizeCgeAtendeStatus(mixed $value, ?string $projectNumber): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalized = CgeAtendeStatus::tryFrom(mb_strtoupper(trim((string) $value)));

        if ($normalized === null) {
            Log::warning('spreadsheet.import.cge_atende_ticket_invalid', [
                'project_number' => $projectNumber,
                'value' => $value,
            ]);
        }

        return $normalized?->value;
    }

    /**
     * Normaliza o valor bruto da coluna "DELIBERAÇÃO" para o enum DeliberationType.
     * A planilha usa o label de exibição (ex.: "LOTE CGE"), não o backing value (BATCH_CGE);
     * tenta casar por backing value e, se falhar, pelo label antes de descartar (null) e logar.
     */
    private function normalizeDeliberationType(mixed $value, ?string $projectNumber): ?string
    {
        if (blank($value)) {
            return null;
        }

        $normalizedValue = mb_strtoupper(trim((string) $value));

        $normalized = DeliberationType::tryFrom($normalizedValue)
            ?? collect(DeliberationType::cases())
                ->first(fn (DeliberationType $case) => mb_strtoupper($case->label()) === $normalizedValue);

        if ($normalized === null) {
            Log::warning('spreadsheet.import.deliberation_type_invalid', [
                'project_number' => $projectNumber,
                'value' => $value,
            ]);
        }

        return $normalized?->value;
    }

    private function rejectedInvalidValue(?string $normalized, mixed $rawValue): bool
    {
        return $normalized === null && ! blank($rawValue);
    }

    /**
     * Sincroniza a aba de Orçamento com o model Budget.
     * Retorna o número de registros gravados.
     */
    public function syncBudget(string $spreadsheetId, string $sheetName, int $userId): int
    {
        $config = config('spreadsheet_mappings.orcamento');
        $columnMap = $config['column_map'];
        $projectLookupColumn = $config['column_for_project_lookup'];

        ['rows' => $rows] = $this->fetchSheet($spreadsheetId, $sheetName);

        $projects = $this->preloadProjectsByNumber($rows, $projectLookupColumn);

        $count = 0;
        foreach ($rows as $row) {
            $project = $projects->get($row[$projectLookupColumn] ?? null);

            if (! $project) {
                continue;
            }

            $record = ['created_by' => $userId];
            foreach ($columnMap as $sheetColumn => $modelField) {
                $record[$modelField] = $row[$sheetColumn] ?? null;
            }

            Budget::updateOrCreate(['project_id' => $project->id], $record);
            $count++;
        }

        return $count;
    }

    /**
     * Sincroniza a aba de Pagamento com Opening::creditor_number (cross-tab).
     * A aba Pagamento ainda não tem model próprio sincronizado — só este campo.
     * Retorna o número de registros atualizados.
     */
    public function syncPagamento(string $spreadsheetId, string $sheetName, int $userId): int
    {
        $config = config('spreadsheet_mappings.pagamento');
        $projectLookupColumn = $config['column_for_project_lookup'];
        $creditorNumberColumn = $config['creditor_number_column'];

        ['rows' => $rows] = $this->fetchSheet($spreadsheetId, $sheetName);

        $projects = $this->preloadProjectsByNumber($rows, $projectLookupColumn);

        $count = 0;
        foreach ($rows as $row) {
            $project = $projects->get($row[$projectLookupColumn] ?? null);

            if (! $project) {
                continue;
            }

            $creditorNumber = trim((string) ($row[$creditorNumberColumn] ?? ''));

            $project->opening?->update([
                'creditor_number' => $creditorNumber !== '' ? $creditorNumber : null,
            ]);

            $count++;
        }

        return $count;
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return Collection<string, Project>
     */
    private function preloadProjectsByNumber(array $rows, string $column): Collection
    {
        $numbers = collect($rows)
            ->pluck($column)
            ->filter()
            ->unique()
            ->values()
            ->all();

        return Project::whereIn('number', $numbers)
            ->with('opening')
            ->get()
            ->keyBy('number');
    }

    /**
     * Importa linhas de uma aba para qualquer model Eloquent usando um mapa de colunas declarativo.
     *
     * @param  class-string  $modelClass
     * @param  array<string, string>  $columnMap  chave = label ou id da coluna na planilha; valor = campo fillable do model
     * @param  string|string[]  $uniqueBy  campo(s) usados como chave no upsert; vazio = insert simples
     * @param  array<string, mixed>  $defaults  campos com valor fixo mesclados em cada registro (ex: created_by, process_supervisor_id)
     * @return int número de linhas gravadas
     */
    public function importToModel(
        string $spreadsheetId,
        string $sheetName,
        string $modelClass,
        array $columnMap,
        string|array $uniqueBy = [],
        array $defaults = [],
    ): int {
        ['rows' => $rows, 'lookup' => $lookup] = $this->fetchSheetWithLookup($spreadsheetId, $sheetName);

        $records = [];
        foreach ($rows as $row) {
            $record = $defaults;
            foreach ($columnMap as $sheetColumn => $modelField) {
                $rowKey = $lookup[$sheetColumn] ?? null;
                $record[$modelField] = $rowKey !== null ? ($row[$rowKey] ?? null) : null;
            }
            $records[] = $record;
        }

        if (empty($records)) {
            return 0;
        }

        $updateColumns = array_unique(array_merge(array_values($columnMap), array_keys($defaults)));

        if (empty($uniqueBy)) {
            $modelClass::insert($records);
        } else {
            $modelClass::upsert($records, (array) $uniqueBy, $updateColumns);
        }

        return count($records);
    }

    /**
     * Igual a fetchSheet() mas também devolve um lookup label/id → rowKey,
     * permitindo que o columnMap aceite tanto o label quanto o id da coluna.
     *
     * @return array{columns: string[], rows: array<int, array<string, mixed>>, lookup: array<string, string>}
     */
    private function fetchSheetWithLookup(string $spreadsheetId, string $sheetName): array
    {
        $url = sprintf(self::GVIZ_ENDPOINT, $spreadsheetId);

        try {
            $raw = Http::timeout(30)
                ->get($url, ['tqx' => 'out:json', 'sheet' => $sheetName])
                ->throw()
                ->body();
        } catch (ConnectionException|RequestException $e) {
            throw ExternalServiceException::unavailable('Google Sheets', $e);
        }

        $table = $this->decodeTable($this->stripSecurityPrefix($raw));
        $columns = $this->extractColumns($table);
        $rows = $this->extractRows($table, $columns);

        // Monta lookup: label → rowKey e id → rowKey (rowKey = label ?: id)
        $lookup = [];
        foreach ($table['cols'] ?? [] as $index => $col) {
            $rowKey = $columns[$index];
            $label = (string) ($col['label'] ?? '');
            $id = (string) ($col['id'] ?? '');

            if ($label !== '') {
                $lookup[$label] = $rowKey;
            }
            if ($id !== '') {
                $lookup[$id] = $rowKey;
            }
        }

        return ['columns' => $columns, 'rows' => $rows, 'lookup' => $lookup];
    }

    /**
     * Strips the /*O_o*\/\ngoogle.visualization.Query.setResponse(...); wrapper.
     */
    private function stripSecurityPrefix(string $raw): string
    {
        // Extract JSON from inside the outer setResponse( ... ) call.
        if (preg_match('/google\.visualization\.Query\.setResponse\((.*)\);?\s*$/s', $raw, $matches)) {
            return $matches[1];
        }

        // Fallback: try to find the first '{' and last '}' in the string.
        $start = strpos($raw, '{');
        $end = strrpos($raw, '}');

        if ($start === false || $end === false) {
            throw new RuntimeException('Could not locate JSON payload in Google Sheets response.');
        }

        return substr($raw, $start, $end - $start + 1);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeTable(string $json): array
    {
        $decoded = json_decode($json, associative: true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON from Google Sheets: '.json_last_error_msg());
        }

        $table = $decoded['table'] ?? null;

        if (! is_array($table)) {
            throw new RuntimeException('Unexpected Google Sheets response structure: missing "table" key.');
        }

        return $table;
    }

    /** @return string[] */
    private function extractColumns(array $table): array
    {
        return array_map(
            static fn (array $col): string => (string) (($col['label'] ?? '') !== '' ? $col['label'] : ($col['id'] ?? '')),
            $table['cols'] ?? [],
        );
    }

    /**
     * @param  string[]  $columns
     * @return array<int, array<string, mixed>>
     */
    private function extractRows(array $table, array $columns): array
    {
        $rows = [];

        foreach ($table['rows'] ?? [] as $rawRow) {
            $cells = $rawRow['c'] ?? [];
            $mapped = [];

            foreach ($columns as $index => $column) {
                $cell = $cells[$index] ?? null;
                $mapped[$column] = isset($cell['v']) ? $this->parseGvizValue($cell['v']) : null;
            }

            $rows[] = $mapped;
        }

        return $rows;
    }

    /**
     * Converte o formato gviz de data "Date(Y,M,D)" (mês 0-indexado) para "Y-m-d".
     * Outros valores são devolvidos sem alteração.
     */
    private function parseGvizValue(mixed $value): mixed
    {
        if (is_string($value) && preg_match('/^Date\((\d+),(\d+),(\d+)\)$/', $value, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[1], (int) $m[2] + 1, (int) $m[3]);
        }

        return $value;
    }
}
