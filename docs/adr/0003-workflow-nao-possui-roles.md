# ADR 0003 — Roles, autorização e responsabilidade do consumidor

- **Status:** Aceito
- **Data:** 2026-08-14

## Contexto

As definições do Workflow declaram roles para places, transições e notifications. O consumidor, no caso do Equivalencia, já possui o cadastro de roles e permissões. O Workflow não deve criar efeitos colaterais no modelo de autorização da aplicação.

Ao mesmo tempo, uma definição que referencia uma role inexistente não pode ser publicada silenciosamente, pois não poderá ser autorizada corretamente.

## Decisão

Workflow não é dono de roles nem permissões. Ele:

- não cria, remove ou altera roles;
- não cria, remove ou altera permissões;
- não mantém tabelas próprias de usuários associados a roles;
- valida as referências declaradas e consulta a autorização por meio de serviços fornecidos pelo consumidor.

O Equivalencia continua responsável por cadastrar, conceder, revogar e administrar roles e permissões. A lista de roles no Workflow é uma exigência de autorização, não uma instrução de provisionamento.

O consumidor fornecerá um resolver capaz de informar se uma role existe. Role inexistente produz erro explícito e impede sync/publicação. Roles usadas em notifications serão submetidas ao mesmo resolver.

O Workflow não reintroduzirá as tabelas legadas `workflow_role_users`, `workflow_role_emails` e `user_workflow_definition`.

## Consequências

- Publicar ou arquivar uma definição não altera a autorização da aplicação.
- Ambientes com conjuntos de roles diferentes precisam provisionar as roles antes do sync.
- O Workflow permanece independente das tabelas e do pacote de autorização do consumidor.
- O erro deve identificar a role ausente e a definição afetada.

## Alternativas consideradas

- Fazer o Workflow administrar roles: rejeitado porque criaria efeitos colaterais fora do domínio do pacote.
- Consultar diretamente tabelas do consumidor: rejeitado porque acoplaria o Workflow ao mecanismo de autorização local.
- Permitir roles inexistentes e enviar uma lista vazia: rejeitado porque esconderia configuração inválida.

## Histórico consolidado

Este ADR consolida os arquivos anteriormente nomeados `0005-workflow-nao-possui-roles.md` e `0006-validacao-de-roles-pelo-consumidor.md`. A validação específica de roles de notifications também é incorporada ao contrato funcional de notifications.
