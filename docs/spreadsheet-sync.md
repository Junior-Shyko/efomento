# Sincronização de Planilha Google Sheets → Banco de Dados

> Arquivo local — não commitar.

---

## O que foi construído

Um mecanismo unificado para importar dados de múltiplas abas de uma planilha pública do Google Sheets
diretamente para os models Eloquent, via comando Artisan ou endpoint HTTP.

### Arquivos envolvidos

| Arquivo | Papel |
|---|---|
| `app/Services/GoogleSheetsService.php` | Busca e parseia a planilha; contém a lógica de sync por aba (`importSheet`, `syncFormalization`, `syncBudget`, `syncPagamento`) |
| `app/Services/SpreadsheetImportService.php` | Processa linhas da aba Abertura: cria/atualiza Agent, Category, Project, Opening e o usuário Fiscal (`User`/`OpeningSupervisor`) |
| `app/Console/Commands/ImportGoogleSheetsCommand.php` | Comando Artisan unificado — aceita `--aba` para escolher quais abas importar |
| `app/Jobs/SyncOpeningRegistrationDataJob.php` | Job assíncrono (fila `details`) que busca a ficha de inscrição via `MapasClient::registrationDetails()` e grava em `Opening.registration_data` |
| `config/spreadsheet_mappings.php` | Mapeamento `coluna da planilha → campo do model` por aba |

> **Planejado, não implementado**: o endpoint HTTP (`app/Http/Controllers/SpreadsheetSyncController.php` e as rotas `POST /sync/formalizacao` / `POST /sync/orcamento`) ainda não existe no repositório. Só o comando Artisan foi implementado até agora — ver seção "Como executar".

---

## Como funciona

**Aba Abertura** (cria entidades novas + atualiza Opening + resolve o Fiscal):
```
Planilha → fetchSheet() → rows[] por label
        → SpreadsheetImportService::processRow()
        → cria/atualiza Agent (CPF ou CNPJ), Category, Project
        → resolveSupervisor(): User::where('name', row['FISCAL'])->first() ou cria via UserService
        → resolveOpening(): Opening::updateOrCreate(['project_id' => $id], $record)
                             + assignSupervisors() → OpeningSupervisor (fiscal titular)
```

> **Atenção — Opening já existe antes do resolveOpening rodar**: `ProjectObserver::created()` cria um `Opening` vazio (`$project->openings()->create()`) assim que o `Project` é criado dentro do mesmo `processRow()`. Por isso `resolveOpening()` usa `Opening::updateOrCreate()` (não `firstOrCreate()`) — com `firstOrCreate` o registro já existente faria o Eloquent ignorar silenciosamente o array de valores, deixando `opening_date`, `agent_status`, `opened_by`, `bank`, etc. sempre em branco. A planilha sempre sobrescreve os campos do Opening a cada execução (mesmo comportamento de Formalização/Orçamento abaixo).

> **Fiscal (`FISCAL`)**: `resolveSupervisor()` busca um `User` por `name` igual ao valor da coluna `FISCAL`. Se não encontrar, cria um novo `User` via `UserService::create()` com `cpf` = coluna `CPF FISCAL`, `registration_number` = coluna `MATRICULA DO FISCAL`, senha aleatória (`Str::password(32)`) e role `monitoring` — a planilha não tem e-mail do fiscal, então é gerado um sintético: `Str::slug(nome, '.').'@mail.com'`. O usuário resolvido preenche `Opening::supervisor_id` **e** é registrado como fiscal titular via `Opening::assignSupervisors()` (mesmo método usado por `ProjectSupervisorService`), para aparecer corretamente nos documentos gerados (`[fiscal_name]`) e nas regras de negócio da etapa. Se a coluna `FISCAL` vier vazia numa linha, `supervisor_id` é limpo (`null`), mas o vínculo já existente em `OpeningSupervisor` **não** é desfeito automaticamente.

> **`registration_data` (ficha de inscrição via API do Mapas) — opt-in, requer `--with-registration-data`**: `processRow()` já extrai o id numérico da inscrição (`extractRegistrationId()`, a partir da URL na coluna `LINK FICHA DE INSCRIÇÃO` — mesmo valor gravado em `Project::registration_id`, sem o prefixo `"on-"`). Se a flag `--with-registration-data` for passada ao comando, `SyncOpeningRegistrationDataJob` é despachado (`afterCommit()`, fila `details`) para cada linha, chama `MapasClient::registrationDetails($registrationId)` e grava um subconjunto seguro da resposta (`registration.id/number/status/files`, `fileConfigurations`, `fields`) em `Opening.registration_data` (json). **Sem a flag, o campo fica `null`** — não é preenchido por padrão, para não disparar uma chamada à API do Mapas por linha em toda importação. Como o job é assíncrono, também é necessário que o worker da fila `details` (`efomento-queue`) esteja rodando para o campo ser efetivamente preenchido. No frontend, a Tab de Abertura (`OpeningTab.vue`) exibe os campos de `registration_data.fields` diretamente no painel esquerdo "Dados disponíveis para consulta" — seção "Ficha de Abertura (Mapas Cultural)" em `resources/js/Schemas/Opening/viewSections.js`, junto com os demais campos da Abertura (não é um botão/dialog separado, diferente do padrão usado em Monitoramento). O parse do valor bruto de cada campo (`valueField`, que a API do Mapas retorna como string JSON-encoded) usa `resources/js/Schemas/parseRegistrationFieldValue.js`.

**Abas Formalização, Orçamento e Pagamento** (enriquece projetos/Opening existentes):
```
Planilha → fetchSheet() → rows[] por label
        → GoogleSheetsService::syncFormalization() / syncBudget() / syncPagamento()
        → lê config/spreadsheet_mappings.php
        → Project::where('number', row['CÓDIGO INSCRIÇÃO MAPAS'])
        → Formalization/Budget::updateOrCreate(['project_id' => $id], $record)
        → (Pagamento) Opening::where('project_id', $id)->update(['creditor_number' => ...])
```

> Datas no formato gviz `Date(Y,M,D)` são convertidas automaticamente para `Y-m-d` antes de chegar ao Eloquent.

### Campos derivados (não vêm da planilha)
- `project_id` → resolvido via `Project::where('number', row['CÓDIGO INSCRIÇÃO MAPAS'])`
- `process_supervisor_id` → `--user-id` do comando / `Auth::id()` via HTTP (somente Formalização)
- `created_by` → `--user-id` do comando / `Auth::id()` via HTTP

### Regras de negócio
- Formalização: linhas com `STATUS = 'Desclassificado'` ou `STATUS = 'Desistente'` são ignoradas
- Abertura: aceita CPF (11 dígitos) e CNPJ (14 dígitos) no campo `CPF / CNPJ DO PROPONENTE`

### Campos cross-tab (não vêm da aba Abertura)
Alguns campos de `Opening` vêm de outras abas — a planilha viva reorganizou colunas que originalmente pareciam pertencer à Abertura:

- **`opening_nup`** — a coluna `N° DO PROCESSO (NUP)` **não existe mais na aba Abertura**, foi movida para a aba **Formalização** (o CSV de amostra local `database/importSheet/musica_abertura.csv` ainda tem essa coluna em Abertura e está desatualizado). É atualizado em `GoogleSheetsService::syncFormalization()`, lendo a coluna configurada em `spreadsheet_mappings.formalizacao.opening_nup_column`.
- **`creditor_number`** — vem da coluna `Nº CREDOR` na aba **Pagamento**. É atualizado em `GoogleSheetsService::syncPagamento()`, lendo a coluna configurada em `spreadsheet_mappings.pagamento.creditor_number_column`. Essa aba ainda não sincroniza o model `Payment` (só este campo em `Opening`) — ver "Abas pendentes" mais abaixo.

Isso significa que rodar só `--aba="Abertura"` nunca preenche `opening_nup` nem `creditor_number` — é necessário rodar também `--aba="Formalização"` e `--aba="Pagamento"`, respectivamente.

---

## Mapeamento atual — Abertura

| Coluna na planilha | Campo no banco (`openings`) |
|---|---|
| DATA ABERTURA DE PROCESSO | `opening_date`, `started_at` |
| STATUS | `agent_status` |
| RESPONSÁVEL POR ABRIR PROCESSO | `opened_by` |
| BANCO | `bank` |
| TIPO DE CONTA | `account_type` |
| AGÊNCIA | `branch` |
| CONTA | `account` |
| DATA DE CERTIDÃO GERADA | `certificate_date` |
| FISCAL | `supervisor_id` (via lookup/criação de `User`) + `OpeningSupervisor` (fiscal titular) |
| CPF FISCAL | `users.cpf` *(só na criação do Fiscal)* |
| MATRICULA DO FISCAL | `users.registration_number` *(só na criação do Fiscal)* |

> `opening_nup` e `creditor_number` **não** estão nessa lista — vêm de outras abas, ver "Campos cross-tab" acima.

Também alimenta `profile_snapshots` (via `buildSnapshotData()`, aplicado tanto no `Agent` quanto no `Project`):

| Coluna na planilha | Campo no banco (`profile_snapshots`) |
|---|---|
| E-MAIL SECUNDÁRIO DO PROPONENTE | `secondary_email` |

---

## Mapeamento atual — Formalização

| Coluna na planilha | Campo no banco (`formalizations`) |
|---|---|
| CÓDIGO INSCRIÇÃO MAPAS | *(lookup do `project_id`)* |
| N° DO PROCESSO (NUP) | *(cross-tab → `openings.opening_nup`, não é campo de `formalizations`)* |
| DATA TRAMITAÇÃO FINALÍSTICA > ASJUR | `asjur_finalistic_processing_date` |
| NÚMERO DO TERMO | `term_number` |
| TERMO DE FOMENTO ENVIADO PARA ASSINATURA DO PROPONENTE (DATA) | `term_signature_sent_at` |
| TERMO DE FOMENTO ASSINADO PELO PROPONENTE (DATA) | `term_signed_at` |
| DATA TRAMITAÇÃO ASJUR > GAB | `sent_to_office_at` |
| DATA DE ASSINATURA DO TERMO PELA SECRETÁRIA | `data_sign_gabinete` |
| N° SACC | `sacc_number` |
| CHAMADO CGE ATENDE | `cge_atende_ticket` |
| DELIBERAÇÃO | `deliberation` |
| DATA DE ENVIO PARA CASA CIVIL | `sent_to_chief_of_staff_at` |
| DATA DE PUBLICAÇÃO NO DOE | `official_gazette_published_at` |
| DATA DE INÍCIO DE VIGÊNCIA DO INSTRUMENTO | `validity_start_at` |
| DATA DE TÉRMINO INICIAL DA VIGÊNCIA DO INSTRUMENTO | `validity_end_at` |

---

## Mapeamento atual — Orçamento

| Coluna na planilha | Campo no banco (`budgets`) |
|---|---|
| CÓDIGO INSCRIÇÃO MAPAS | *(lookup do `project_id`)* |
| DATA TRAMITAÇÃO CODIP > COAFI | `processing_date_for_coafi` |
| DATA RECEBIMENTO CODIP | `processing_date_for_codip` |

---

## Mapeamento atual — Pagamento

| Coluna na planilha | Campo no banco |
|---|---|
| CÓDIGO INSCRIÇÃO MAPAS | *(lookup do `project_id`)* |
| Nº CREDOR | `openings.creditor_number` *(cross-tab; model `Payment` ainda não é sincronizado)* |

---

## Como executar

### 1. Rodar a migration (apenas uma vez)

```bash
docker exec <container_php> php artisan migrate
```

### 2. Via comando Artisan (recomendado)

```bash
docker exec <container_php> php artisan app:import-google-sheets \
  ID_DA_PLANILHA \
  --aba="Abertura" --aba="Formalização" --aba="Orçamento" --aba="Pagamento" \
  --user-id=<id_do_usuario> \
  --notice-id=<id_do_edital>   # fallback quando o edital não é encontrado pelo external_id
```

Opções disponíveis:

| Opção | Obrigatório | Descrição |
|---|---|---|
| `--aba` | não (padrão: todas) | Abas a importar. Pode repetir para múltiplas. |
| `--user-id` | **sim** | ID do usuário para `created_by` / `user_id` |
| `--notice-id` | não | ID do edital fallback (necessário para Abertura quando `external_id` não bate) |
| `--with-files` | não | Baixar arquivos do MAPAS ao importar projetos (apenas Abertura) |
| `--with-registration-data` | não | Sincroniza `Opening.registration_data` via API do Mapas (`MapasClient::registrationDetails()`), assíncrono na fila `details` (apenas Abertura) |

### 3. Via endpoint HTTP (planejado, não implementado)

Ainda não existe `SpreadsheetSyncController` nem as rotas abaixo em `routes/web.php` — o exemplo mostra a interface **planejada**, para quando for implementada:

```bash
curl -X POST https://<seu-dominio>/sync/formalizacao \
  -H "Content-Type: application/json" \
  -H "X-CSRF-TOKEN: <token>" \
  -d '{"spreadsheet_id": "ID_DA_PLANILHA_AQUI"}'
```

Rotas planejadas: `POST /sync/formalizacao`, `POST /sync/orcamento`

### 4. Inspecionar colunas via Tinker (diagnóstico)

```bash
docker exec -it <container_php> php artisan tinker
```
```php
$s = app(App\Services\GoogleSheetsService::class);
$data = $s->fetchSheet('ID_DA_PLANILHA', 'Abertura');
// Ver colunas reais:
foreach ($data['columns'] as $i => $col) { echo "[$i] $col\n"; }
// Ver primeira linha:
print_r($data['rows'][0]);
```

> **Como encontrar o ID da planilha:**
> Na URL `https://docs.google.com/spreadsheets/d/**ID_AQUI**/edit`, o ID é a parte entre `/d/` e `/edit`.

> **Requisito:** A planilha precisa estar com acesso "Qualquer pessoa com o link pode visualizar".

---

## Como adicionar uma nova aba

1. **Adicionar o mapeamento** em `config/spreadsheet_mappings.php`:
```php
'monitoramento' => [
    'column_for_project_lookup' => 'CÓDIGO INSCRIÇÃO MAPAS',
    'column_map' => [
        'COLUNA DA PLANILHA' => 'campo_do_model',
    ],
],
```

2. **Adicionar método** em `GoogleSheetsService`:
```php
public function syncMonitoramento(string $spreadsheetId, string $sheetName, int $userId): int
{
    $config = config('spreadsheet_mappings.monitoramento');
    // ... mesmo padrão de syncFormalization / syncBudget
    // (ou padrão cross-tab, como em syncPagamento(), se o campo pertencer a outro model)
}
```

3. **Registrar no comando** em `ImportGoogleSheetsCommand` (const `ABA_NAMES` + bloco `match`):
```php
'Monitoramento' => $service->syncMonitoramento($spreadsheetId, $aba, $userId),
```

4. **Adicionar método no controller** (para o endpoint HTTP — ver nota no topo do doc: ainda não implementado):
```php
public function syncMonitoramento(Request $request): RedirectResponse
{
    $count = $this->sheets->syncMonitoramento(..., userId: Auth::id());
    return back()->with('success', "{$count} registros sincronizados.");
}
```

5. **Adicionar rota** em `routes/web.php`:
```php
Route::post('/monitoramento', [SpreadsheetSyncController::class, 'syncMonitoramento'])
    ->name('monitoramento');
```

---

## Migração histórica de arquivos e registration_data (planilhas → produção)

Runbook para a carga única de todas as planilhas históricas, incluindo os arquivos anexados no Mapas Cultural (documentos da inscrição) e a ficha de inscrição (`Opening.registration_data`).

**Estratégia definida:** todo o processo — import da planilha, download dos arquivos **e** sync de `registration_data` — roda em homologação. Depois, o `.sql` completo do banco é restaurado em produção e a infraestrutura transfere os arquivos físicos de homologação para produção via `rsync`. Não é necessário nenhum comando artisan dedicado — as flags `--with-files` e `--with-registration-data`, já existentes em `app:import-google-sheets`, resolvem sozinhas, pelos seguintes motivos:

- `DownloadMapasProjectFileJob` grava cada arquivo em path **relativo**: `projects/{project_id}/mapas/{external_id}.{ext}`, no disco `public` (root `storage/app/public`, ver `config/filesystems.php`).
- `SyncOpeningRegistrationDataJob` grava o subconjunto seguro da ficha diretamente em `Opening.registration_data` (json) — sem nenhuma dependência de storage/arquivo físico, então não precisa de `rsync`: o dump SQL já carrega esse dado sozinho.
- Como produção restaura o `.sql` **inteiro** do banco (não é merge/insert em banco já existente), os `id` de `projects` ficam idênticos entre homologação e produção — então o path relativo gravado na tabela `files` (que vai junto no mesmo dump) aponta para o lugar certo assim que o `rsync` copiar os arquivos.
- Os dois pipelines (`LoadSpreadsheetProjectFilesJob`→`SyncProjectFilesJob`→`DownloadMapasProjectFileJob` e `SyncOpeningRegistrationDataJob`) são os mesmos usados no fluxo em tempo real: já têm rate limit (`mapas-api`, 60 req/min) e `DownloadMapasProjectFileJob` é idempotente (verifica `StoredFile::withTrashed()` antes de baixar de novo).

### Passo a passo

1. Rodar os comandos de import em **homologação**, com `--with-files` e `--with-registration-data`:
   ```bash
   docker exec <container_php> php artisan app:import-google-sheets ID_DA_PLANILHA \
     --aba="Abertura" --aba="Formalização" --aba="Orçamento" --aba="Pagamento" \
     --user-id=<id_do_usuario> --notice-id=<id_do_edital> --with-files --with-registration-data
   ```

2. **Esperar a fila `details` (e, em cascata, `files`) esvaziarem antes de gerar o dump SQL.** `--with-files` e `--with-registration-data` só *disparam* os jobs (assíncronos, todos entram primeiro na fila `details`: `LoadSpreadsheetProjectFilesJob` e `SyncOpeningRegistrationDataJob`; o primeiro encadeia `SyncProjectFilesJob`/`DownloadMapasProjectFileJob` na fila `files`) — se o dump for tirado antes dos workers terminarem, o `.sql` vai ter os `Project`/`Opening` mas faltar `files` e/ou `Opening.registration_data` de itens ainda em andamento. Não há Horizon no projeto; confirmar fila vazia por:
    - `job_batches` das batches `sync-project-files:%` (`pending_jobs = 0`);
    - logs de arquivos (`sync.project.files.queued` vs `sync.project.file.created` / `sync.project.file.failed` / `sync.project.files.empty`) e de registration_data (`sync.opening.registration_data.start` vs `sync.opening.registration_data.done` / `sync.opening.registration_data.failed`) até não sobrar pendência.

3. Gerar o dump `.sql` completo do banco de homologação **só depois** da confirmação acima.

4. Restaurar o `.sql` em produção.

5. Infraestrutura sincroniza `storage/app/public/projects/` de homologação para produção via `rsync` — precisa ser exatamente essa raiz relativa (mesmo disco `public`), senão os paths gravados no banco não resolvem. `registration_data` não precisa de rsync, já veio no dump.

6. Falhas pontuais identificadas nos logs (`sync.project.file.failed` / `sync.opening.registration_data.failed`) podem ser reprocessadas re-executando o import. O comando `app:import-google-sheets` **não tem filtro por inscrição/linha** — ele reprocessa a aba inteira. As duas formas suportadas de reprocessar:
   - **Planilha completa:** rodar o mesmo comando de novo com `--with-files`/`--with-registration-data`. É seguro (`firstOrCreate`/`firstOrNew`/`recordIfChanged` são idempotentes; `DownloadMapasProjectFileJob` deduplica via `StoredFile::withTrashed()`; `SyncOpeningRegistrationDataJob` apenas sobrescreve `registration_data`), mas re-enfileira os jobs para **todas** as linhas, respeitando o rate limit `mapas-api` (60 req/min).
   - **Subconjunto isolado:** criar uma cópia da planilha contendo só as linhas afetadas e rodar o import apontando para essa cópia (`--aba="Abertura"`). Evita re-enfileirar trabalho para o restante.

   Em ambos os casos, repetir os passos 2–4 (aguardar a fila, gerar novo dump, restaurar) para o conjunto reprocessado antes do rsync final.


## Abas implementadas

| Aba | Model(s) | Método no service |
|---|---|---|
| Abertura | `Agent`, `Category`, `Project`, `Opening`, `User`/`OpeningSupervisor` (Fiscal), `Opening.registration_data` (opt-in, `--with-registration-data`, via `SyncOpeningRegistrationDataJob`) | `importSheet()` |
| Formalização | `Formalization`, `Opening` (só `opening_nup`, cross-tab) | `syncFormalization()` |
| Orçamento | `Budget` | `syncBudget()` |
| Pagamento | `Opening` (só `creditor_number`, cross-tab) | `syncPagamento()` |

## Abas pendentes (aguardando confirmação da equipe)

- Parcela → `Installment`
- Pagamento → `Payment` completo (hoje só `Opening.creditor_number` é sincronizado — o model `Payment`, que já tem `creditor_registration_number` e outros campos, ainda não é populado)
- Monitoramento → `Monitoring`
- Prestação de Contas → (a definir)
