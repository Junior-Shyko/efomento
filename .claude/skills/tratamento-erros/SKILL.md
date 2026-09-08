---
name: tratamento-erros
description: Aplica a hierarquia de exceções AppException (backend) e o padrão useErrorHandler (frontend) do efomento sempre que a tarefa envolver lançar/capturar erros, criar um Service/Controller novo, planejar uma feature com regra de negócio que pode falhar, ou tratar erro de API no Vue. Use quando o usuário pedir para "tratar erro", "lançar exception", "adicionar validação de regra de negócio", "capturar erro da API", "onError do form", ou ao planejar qualquer implementação nova — não reinventar try/catch ad-hoc.
---

# Tratamento de erros (AppException + useErrorHandler)

Documentação de referência para o time: `docs/error_handling.md`. Origem/diagnóstico + decisão completa: `docs/erros_plan.md`. Este skill resume o padrão já em produção — **leia a seção 3.2 de `docs/erros_plan.md` antes de assumir que algo já foi migrado**, pois a migração é incremental e alguns pontos ainda estão pendentes (ver Passo 4 abaixo).

## Passo 1 — Ler as referências vivas (OBRIGATÓRIO antes de gerar código)

| Arquivo | O que observar |
|---|---|
| `app/Exceptions/AppException.php` | Base abstrata: `userMessage`, `httpStatus` (default 422), `context()`, `shouldReport()` |
| `app/Exceptions/Domain/StageTransitionException.php` | Subclasse com factory estática (`invalidStatus`) e `shouldReport()` sobrescrito para `false` (erro de fluxo esperado, não polui o Sentry) |
| `app/Exceptions/Domain/BusinessRuleException.php` | Subclasse "vazia" — substituto direto de `\InvalidArgumentException` genérica, sem precisar de factory por regra |
| `app/Exceptions/Domain/DomainAuthorizationException.php` | `httpStatus` fixo em 403 no construtor |
| `app/Exceptions/Integration/ExternalServiceException.php` | `unavailable(service, $previous)` para wrap de exception técnica; `fromFailedResponse(service, message, context)` quando não há `$previous` (ex: `$response->failed()`) |
| `bootstrap/app.php` (`withExceptions`) | Handler central: `reportable` por `shouldReport()`, `render` único para toda `AppException` (JSON 422+ se `expectsJson() && !X-Inertia`, senão `back()->withErrors(['message' => ...])`) |
| `app/Services/ProjectStageService.php` | Exemplar de uso das 3 subclasses de domínio em um Service real |
| `app/Http/Controllers/ProjectStageController.php` | Exemplar de controller **sem** try/catch (Handler resolve) — método `advance` — e a exceção documentada: `return()` mantém `catch (AppException $e)` porque `ReturnProcessModal.vue` depende de `back()->with('error', ...)`, não `withErrors` |
| `app/Services/GoogleSheetsService.php` | Exemplar de `ExternalServiceException::unavailable()` envolvendo `RequestException`/`ConnectionException` |

## Passo 2 — Backend: qual exception lançar

Duas fronteiras (não criar subclasse nova por regra — granularidade fica na mensagem/`context()`, não na classe):

- **Regra de negócio que o usuário entende e pode agir** (etapa bloqueada, limite atingido, campo obrigatório de negócio): `Domain\BusinessRuleException` (genérica) ou `Domain\StageTransitionException`/`Domain\DomainAuthorizationException` se o caso já tem factory própria. Mensagem pronta em **português** para exibir direto ao usuário.
- **Falha técnica/de integração externa** (Guzzle, timeout do Google Sheets/Mapas Cultural, qualquer chamada HTTP a serviço de terceiro): capturar a exception técnica original e **envolver** (`$previous`) em `Integration\ExternalServiceException`. Nunca deixar `RequestException`/`ConnectionException` subir crua.
- **Nunca** lançar `\InvalidArgumentException`/`\Exception`/`\RuntimeException` genérica para erro que o usuário vai ver — isso é exatamente o padrão antigo que este plano substituiu.
- **`ValidationException` e `AuthorizationException` do Laravel continuam nativas** — não entram nessa hierarquia, não ganham `render()` novo, continuam alimentando `form.errors` do Inertia normalmente. Não envolver essas duas em `AppException`.
- **Controllers ficam finos**: não adicionar `try/catch` novo. O Handler central em `bootstrap/app.php` resolve qualquer `AppException` lançada por Service/FormRequest. Só existe uma exceção conhecida a esse padrão (ver `ProjectStageController::return()` acima) — replicá-la somente se o Vue de destino já depender do contrato `back()->with('error', ...)`.
- **Reportar ou não ao Sentry**: por padrão `shouldReport()` retorna `true` (herdado de `AppException`). Sobrescrever para `false` só quando o erro é fluxo esperado de negócio (como `StageTransitionException`), nunca para esconder bug real.

## Passo 3 — Frontend: qual handler usar

**Atenção**: `resources/js/Composables/useErrorHandler.js` ainda **não existe** no repo (passo 5 do plano está pendente — confirme com `find resources/js/Composables -iname "*error*"` antes de assumir que existe). Se a tarefa exigir tratar erro em uma Tab/tela nova:

- **Se `useErrorHandler.js` já existir** (verifique primeiro): usar `handleInertiaError(errors, fallback)` em `onError` do `useForm`/router do Inertia, e `handleAxiosError(error, fallback)` em `catch` de chamada axios (ex: `useLegalAnalysis.js`). Não reimplementar `Object.values(errors).join(', ')` inline.
- **Se ainda não existir**: seguir o padrão hoje em vigor nas Tabs existentes — `onError: (errors) => showSnackbar(Object.values(errors).flat().join(', ') || '<fallback>', 'error')` (ver `resources/js/Pages/ProjectDetails/Partials/Tabs/FormalizationTab.vue`) — e avisar o usuário que criar `useErrorHandler.js` é um passo separado do plano (`docs/erros_plan.md`, seção 4), não implementar por conta própria sem alinhar, já que passa a ser consumido por várias Tabs de uma vez.
- Mensagem exibida ao usuário sempre em **português**; código/nomes de função em **inglês**.

## Passo 4 — Checar o estado real da migração antes de mexer

Não presumir que um Controller/Service específico já foi migrado. Rodar (ou pedir para o usuário confirmar) antes de planejar:

```bash
grep -rn "InvalidArgumentException\|catch (\\\\Throwable\|catch (\\\\Exception" app/Http/Controllers app/Services
```

Pendências conhecidas na última verificação (ver seção 3.2 de `docs/erros_plan.md` para o estado atualizado): `UserController` e `OpeningController` ainda têm `catch` a remover; `useErrorHandler.js` e Sentry no frontend ainda não foram criados.

## Passo 5 — Checklist de conformidade

- [ ] Nenhuma `\InvalidArgumentException`/`\Exception`/`\RuntimeException` genérica lançada para erro visível ao usuário — usar subclasse de `AppException`
- [ ] Exception técnica de integração (Guzzle, HTTP externo) envolvida em `ExternalServiceException` com `$previous`, nunca propagada crua
- [ ] `ValidationException`/`AuthorizationException` do Laravel deixadas intactas, sem wrap
- [ ] Controller não ganhou `try/catch` novo (exceto o caso documentado de `back()->with('error', ...)`)
- [ ] `shouldReport()` só é `false` para erro de fluxo esperado, nunca para mascarar bug
- [ ] Frontend: confirmado se `useErrorHandler.js` existe antes de decidir o padrão a seguir; mensagem de erro em português
- [ ] Se a tarefa tocou em algo listado como pendente na seção 3.2 do plano, atualizar essa seção ao final