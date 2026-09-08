<?php

namespace App\Console\Commands;

use App\Services\GoogleSheetsService;
use Illuminate\Console\Command;
use RuntimeException;

class ImportGoogleSheetsCommand extends Command
{
    protected $signature = 'app:import-google-sheets
                            {spreadsheet-id : ID da planilha pública do Google Sheets}
                            {--aba=* : Abas a importar: Abertura, Formalização, Orçamento, Pagamento}
                            {--user-id= : ID do usuário para created_by / user_id}
                            {--notice-id= : ID do edital fallback quando não encontrado pela planilha (apenas Abertura)}
                            {--with-files : Baixar arquivos do MAPAS ao importar projetos (apenas Abertura)}
                            {--with-registration-data : Sincroniza registration_data do Opening via API do Mapas ao importar a aba Abertura}';

    protected $description = 'Importa dados de uma ou mais abas do Google Sheets para o banco';

    private const ABA_NAMES = ['Abertura', 'Formalização', 'Orçamento', 'Pagamento'];

    public function handle(GoogleSheetsService $service): int
    {
        $spreadsheetId = $this->argument('spreadsheet-id');
        $abas = $this->option('aba') ?: self::ABA_NAMES;
        $userId = $this->option('user-id') ? (int) $this->option('user-id') : null;
        $noticeId = $this->option('notice-id') ? (int) $this->option('notice-id') : null;
        $withFiles = (bool) $this->option('with-files');
        $withRegistrationData = (bool) $this->option('with-registration-data');

        if ($userId === null) {
            $this->error('Informe o usuário com --user-id=<id>.');

            return self::FAILURE;
        }

        $failed = false;

        foreach ($abas as $aba) {
            $this->info("Importando aba \"{$aba}\"...");

            try {
                $count = match ($aba) {
                    'Abertura' => $service->importSheet($spreadsheetId, $aba, $withFiles, $userId, $noticeId, $withRegistrationData),
                    'Formalização' => $service->syncFormalization($spreadsheetId, $aba, $userId),
                    'Orçamento' => $service->syncBudget($spreadsheetId, $aba, $userId),
                    'Pagamento' => $service->syncPagamento($spreadsheetId, $aba, $userId),
                    default => throw new RuntimeException("Aba \"{$aba}\" não reconhecida. Use: ".implode(', ', self::ABA_NAMES)),
                };

                $this->info("  {$count} registro(s) gravado(s).");
            } catch (RuntimeException $e) {
                $this->error("  Erro: {$e->getMessage()}");
                $failed = true;
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
