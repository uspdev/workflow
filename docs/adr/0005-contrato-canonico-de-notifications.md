# ADR 0005 — Contrato funcional de notifications V2

- **Status:** Aceito
- **Data:** 2026-08-14

## Contexto

O campo `notifications` aparecia na documentação com cardinalidade ambígua: parte do texto o tratava como lista e os exemplos usavam um único objeto. Decisões antigas também alternaram entre deixar notifications fora do escopo e rejeitar qualquer configuração.

O escopo atual inclui notifications, mas a prioridade é manter um contrato pequeno, baseado nas roles que o Workflow já conhece e sem funcionalidades sem uso identificado no Equivalencia.

## Decisão

`notifications` será um único objeto opcional por transição, nunca uma lista de objetos. Quando omitido ou vazio, a configuração adicional é considerada ausente.

As roles dos places indicados em `tos` são sempre os destinatários padrão. `append_roles` acrescenta roles a essa lista. O objeto é limitado a essas referências de roles:

- `tos`: roles padrão derivadas dos destinos da transição;
- `append_roles`: roles adicionais.

`override_roles`, `users` e `emails` não fazem parte do Workflow V2. Se qualquer um desses campos aparecer, o sync produzirá erro explícito e não persistirá nem publicará a definição.

Todas as roles dos places, de `tos` e de `append_roles` serão validadas pelo resolver do consumidor. Role inexistente é erro de configuração.

O Workflow V2 não terá etapa de deduplicação. Repetições de roles ou destinatários resultantes da definição ou de mais de uma origem serão preservadas conforme a configuração e o comportamento posterior do consumidor.

Há duas situações distintas de ausência de destinatários:

- se nenhuma role for calculada, não se publica notification nem se cria trabalho de entrega; a transição continua válida;
- se uma role válida não possuir membros no processamento, nada é enviado para ela, não há retry apenas por esse motivo e a transição não é desfeita.

O Workflow calcula e publica referências de roles; não resolve membros, usuários ou e-mails.

## Consequências

- O contrato JSON tem uma única representação oficial.
- O Workflow V2 precisa resolver e validar apenas roles.
- O contrato não exige identificadores de usuários nem validação de e-mails.
- Notifications sem destinatário potencial não geram trabalho assíncrono inútil.
- Uma role temporariamente vazia não transforma uma transição válida em falha do Workflow.
- Uma futura extensão para substituição de roles, usuários ou e-mails exigirá decisão própria.

## Alternativas consideradas

- Manter notifications fora do escopo: rejeitado; notifications foi trazida ao escopo funcional.
- Aceitar uma lista e um objeto: rejeitado porque perpetuaria a ambiguidade.
- Suportar `override_roles` preventivamente: rejeitado por adicionar complexidade sem uso identificado.
- Suportar users/emails: rejeitado no V2 por exigir resolvers e regras adicionais sem necessidade funcional atual.
- Deduplicar automaticamente: rejeitado porque adicionaria complexidade sem retorno significativo para o escopo atual.
- Publicar evento vazio: rejeitado porque criaria trabalho sem destinatário potencial.
- Repetir o envio de role válida sem membros: rejeitado porque não há destinatário concreto e a transição já foi confirmada.

## Histórico consolidado

Este ADR consolida e atualiza os arquivos anteriormente nomeados `0008-notificacoes-fora-do-escopo-atual.md`, `0009-rejeitar-notificacoes-nao-suportadas.md`, `0010-contrato-canonico-de-notifications.md`, `0011-validacao-de-roles-em-notifications.md`, `0016-validacao-de-emails-fora-do-escopo-atual.md`, `0018-exclusividade-entre-override-e-append-roles.md`, `0019-retirar-override-roles-do-escopo-v2.md`, `0021-destinatarios-padrao-de-notifications.md`, `0022-deduplicacao-de-destinatarios.md`, `0023-nao-deduplicar-destinatarios.md`, `0024-notifications-v2-apenas-por-roles.md`, `0027-role-sem-membros-nao-envia-notification.md` e `0028-transicao-sem-destinatarios-nao-publica-notification.md`. As decisões históricas posteriormente substituídas permanecem descritas nas alternativas e não são o contrato vigente.
