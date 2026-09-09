# e-Fomento

[![codecov](https://codecov.io/gh/secultce/efomento/graph/badge.svg?token=B8H2FVK1PN)](https://codecov.io/gh/secultce/efomento)

Sistema web de gerenciamento de processos de fomento cultural da **Secretaria da Cultura do Ceará (SECULT-CE)**, construído em Laravel 13.

Substitui a planilha compartilhada "Planilão" por um fluxo digital estruturado, seguro e auditável para acompanhar editais culturais pós-seleção no Mapa Cultural do Ceará. Gerencia o ciclo completo de projetos aprovados em editais (ex: Ciclo Cearense Carnavalesco, PNAB), desde a abertura do processo até o pagamento final e monitoramento.

## Stack Tecnológica

| Camada                 | Tecnologia                                      |
|------------------------|--------------------------------------------------|
| Backend                | Laravel 13 (PHP 8.3+)                           |
| Banco de dados         | PostgreSQL (JSONB para campos flexíveis)         |
| Admin interno          | Filament PHP (acesso direto aos models)          |
| Frontend usuário final | Vue.js + Vuetify + Tailwind CSS (via API RESTful)|
| Autenticação           | Laravel Breeze                                   |
| Build                  | Vite 7, Node.js                                  |
| Testes                 | PHPUnit 11 (SQLite in-memory)                    |
| Lint                   | Laravel Pint                                     |
| Infra                  | Docker (PHP-FPM, Nginx, PostgreSQL, Vite)        |

## Funcionalidades

### Importação de Dados
- Importação inicial de projetos/agentes via API do Mapa Cultural do Ceará
- Sincronização sob demanda (refresh) com resiliência — sistema funciona offline se a API estiver indisponível

### Workflow Multi-Etapas
Fluxo sequencial com etapas dedicadas por setor:

| Etapa                  | Model           | Setor Responsável | Escopo                                              |
|------------------------|-----------------|-------------------|------------------------------------------------------|
| **Abertura**           | `Opening`       | C. Finalística    | Dados do agente, endereço, documentos, dados bancários, parcelas |
| **Análise Jurídica**   | `LegalAnalysis` | ASJUR             | Regularidade, certidões, impedimentos, pareceres     |
| **Formalização**       | `Formalization` | —                 | Formalização contratual, número do contrato          |
| **Orçamento**          | `Budget`        | CODIP             | Definição de parcelas, valores, cronograma           |
| **Pagamento**          | `Payment`       | CEGEF             | Comprovantes, liberações                             |
| **Monitoramento**      | `Monitoring`    | —                 | Prestação de contas                                  |

- Cada setor edita apenas sua etapa
- Etapa só avança após aprovação da anterior
- Histórico de alterações e auditoria em todos os campos críticos

### Administração (Filament)
- Cadastro de editais, usuários, permissões e configurações
- Painel de gestão e suporte operacional para equipe técnica

### Interface do Usuário Final (Vue.js)
- Telas de acompanhamento e preenchimento de etapas
- Layout definido pelo time de UX/UI
- Comunicação exclusiva via API RESTful

<div style="display:none">
## Modelo de Dados

```
Notice (edital cultural)
└── Registration (inscrição/projeto — entidade raiz do fluxo)
    ├── Opening              (Abertura — C. Finalística)
    │   └── OpeningSupervisor  (Fiscal atribuído à abertura — pivot user ↔ opening)
    ├── LegalAnalysis        (Análise Jurídica — ASJUR)
    ├── Formalization        (Formalização)
    ├── Budget               (Orçamento — CODIP)
    ├── Payment              (Pagamento — CEGEF)
    └── Monitoring           (Monitoramento/Prestação de Contas)
```
## Diagramas
- Para uso do PlantUml poderá ter que instalar o executável dot.exe na sua máquina o instalar no linux e configurar o 
caminho do Ubuntu que é '/usr/bin/dot'. A instalação é com `sudo apt install graphviz`

</div>

## Como Rodar

### Pré-requisitos
- Docker e Docker Compose

### Subir o projeto

```bash
docker compose up -d
```

Isso inicia automaticamente:

| Container              | Descrição                                     | Porta          |
|------------------------|-----------------------------------------------|----------------|
| efomento-postgres      | PostgreSQL 16 com health check                | 5433 (host)    |
| efomento-app           | PHP-FPM com OPcache + caches Laravel          | 9000 (interno) |
| efomento-vite          | Vite dev server com HMR                       | 5173           |
| efomento-nginx         | Proxy reverso                                 | 8080           |
| efomento-reverb        | Servidor WebSocket para notificações          | 8081           |
| efomento-queue         | Worker de filas (high/medium/details/default) | —              |
| efomento-queue-files   | Worker de filas (files)                       | —              |
| efomento-scheduler     | Laravel scheduler                             | —              |
| efomento-greenmail     | Servidor SMTP/IMAP local (e-mail de teste)    | —              |
| efomento-roundcube     | Webmail para visualizar e-mails de teste      | 8025           |

Acesse: **http://localhost:8080**

### Comandos úteis

```bash
# Migrations
docker compose exec app php artisan migrate

# Testes
docker compose exec app php artisan test

# Teste específico
docker compose exec app php artisan test --filter=NomeDoTeste

# Lint (code style)
docker compose exec app ./vendor/bin/pint

# Artisan / Composer / npm
docker compose exec app php artisan <comando>
docker compose exec app composer <comando>
docker compose exec app npm <comando>
docker exec efomento-app composer require laravel/breeze --dev
docker exec efomento-app php artisan migrate:fresh --seed
docker exec efomento-app php artisan db:seed --class=PermissionSeeder

# Forcar Sincronismo dos editais
docker compose exec app php artisan tinker  --execute="SyncNoticesJob::dispatch()"
```

O comando `php artisan db:seed` (inclusive via `migrate:fresh --seed`) respeita
`SEED_MODE`:

| Valor | Dados criados |
|-------|---------------|
| `none` | Nenhum (padrao) |
| `users` | Permissoes, perfis e usuarios |
| `all` | Todos os dados de desenvolvimento |

Depois de alterar `SEED_MODE` em um ambiente com configuracao em cache, execute
`php artisan config:clear` antes de rodar o seeder.

### Acessos locais

| Serviço       | URL / Host             |
|---------------|------------------------|
| App           | http://localhost:8080  |
| Reverb        | ws://localhost:8081    |
| Vite HMR      | http://localhost:5173  |
| PostgreSQL    | localhost:5433         |
| Webmail       | http://localhost:8025  |

## Arquitetura

### Duas camadas de interface
- **Vue.js + Vuetify + Tailwind** → interface do usuário final. Consome API RESTful.

### Padrões de Projeto
- **MVC** — Resource Controllers + Eloquent Models + Blade (Filament) / Vue (frontend)
- **Service Layer** — lógica de negócio isolada em Services
- **Observers** — cálculos automáticos e side effects
- **Form Requests** — validação centralizada
- **API Resources** — transformação de dados para o frontend Vue

## Licença

Projeto interno da SECULT-CE. Uso restrito.
