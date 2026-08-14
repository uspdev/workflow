# ADR 0002 — Ownership do schema, desacoplamento e histórico do Workflow

- **Status:** Aceito
- **Data:** 2026-08-14

## Contexto

Workflow e Forms podem colaborar no código quando uma transição usa um formulário, mas não devem criar uma dependência estrutural entre seus bancos. Uma foreign key para `form_submissions` ou `users` acoplaria migrations, retenção e autenticação do Workflow a estruturas do consumidor.

O histórico do Workflow também precisa sobreviver à remoção de referências externas e não deve duplicar o conteúdo mantido pelo Forms.

## Decisão

Workflow é o único dono de `workflow_definitions`, `workflow_objects` e `workflow_history`. Forms é o dono de `form_definitions`, `form_submissions` e `activity_log`.

As migrations V2 do Workflow são as únicas migrations carregadas pelo `WorkflowServiceProvider`. O pacote não as publicará para cópia na aplicação. Migrations históricas do consumidor não serão alteradas nem recarregadas; migrations novas do V2 podem ser ajustadas antes da implantação.

O schema do Workflow pode manter foreign keys internas, como a relação entre objeto, definição e histórico. Referências externas a Forms e usuários serão opcionais e não terão foreign keys:

- `workflow_history.form_submission_id` identifica uma submissão quando houver formulário;
- `workflow_history.user_id` identifica o ator quando houver usuário.

O Workflow não terá tabelas próprias para associar roles a usuários ou e-mails.

O histórico será append-only:

- uma transição concluída insere um registro;
- registros existentes não são atualizados ou removidos pelo fluxo normal;
- uma correção, quando necessária, é registrada como novo evento;
- um objeto que possua histórico não pode ser removido em cascata.

O histórico registra a transição e seus metadados próprios, mas não copia o payload do formulário. Forms continua sendo responsável pelo conteúdo e pela auditoria da submissão.

## Consequências

- Workflow e Forms podem evoluir seus schemas sem FK cruzada.
- A integridade das referências externas passa a ser responsabilidade da integração de aplicação.
- Um histórico pode manter uma referência cujo registro externo foi removido.
- A sequência das transições permanece consultável mesmo sem o payload do formulário.
- A remoção de um usuário ou submissão não apaga eventos do Workflow.

## Alternativas consideradas

- FK para `form_submissions`: rejeitada porque acoplaria schema, migrations e retenção dos pacotes.
- FK para `users`: rejeitada porque acoplaria o pacote ao schema de autenticação do consumidor.
- Duplicar o payload do Forms no histórico: rejeitada porque criaria duas fontes de verdade.
- Desacoplamento completo do código: rejeitado nesta etapa por ampliar o escopo além do funcionamento necessário ao Equivalencia.

## Histórico consolidado

Este ADR consolida os arquivos anteriormente nomeados `0003-desacoplamento-do-schema-forms-workflow.md` e `0004-ciclo-de-vida-do-historico-workflow.md`.
