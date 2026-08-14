# ADR 0006 — Fronteira de integração e implementação de notifications

- **Status:** Aceito
- **Data:** 2026-08-14

## Contexto

O Workflow conhece a transição e as roles destinatárias, mas não deve conhecer o modelo de usuário, o conteúdo da mensagem, o Mailable ou a infraestrutura de filas do consumidor. O Equivalencia é responsável por suas associações entre roles e usuários e por seu conteúdo de domínio.

Foram discutidos evento, listener, Mailable, fila `database`, `ShouldQueue`, payload, retry e tratamento de falhas. Esses detalhes não devem ser fixados enquanto Lucas, que possui experiência na implementação de notifications, não definir a solução técnica.

## Decisão

Depois de confirmar a transição e seu histórico, o Workflow deverá disponibilizar ao consumidor um contrato de notification com contexto suficiente para a entrega. O mecanismo concreto — evento, listener, chamada direta ou outra integração — será escolhido e documentado por Lucas.

O consumidor será responsável por:

- resolver os membros atuais das roles no momento do processamento;
- escolher o conteúdo, assunto, template e idioma;
- escolher o Mailable ou outro formato de mensagem;
- escolher e configurar transporte, fila, retry, observabilidade e tratamento de falhas.

O Workflow não armazenará snapshot dos membros no momento da transição. A resolução usará os membros vigentes quando o consumidor processar a notification.

Uma falha posterior na entrega não desfará a transição já confirmada. Uma role válida sem membros não provocará retry apenas por esse motivo. O consumidor poderá criar observabilidade ou alertas próprios, mas isso não faz parte do contrato do Workflow.

O contrato técnico final não será implementado nem especificado por este ADR. Antes da implementação correspondente, Lucas deverá registrar documentação nova com a solução escolhida, incluindo o payload definitivo e as regras operacionais.

## Consequências

- Workflow permanece independente de usuários, conteúdo, e-mail, filas e canais específicos.
- Alterações nos membros das roles não exigem alterações no schema do Workflow.
- O consumidor pode resolver dados atuais, mas não terá garantia de que os destinatários sejam os mesmos do instante da transição.
- A transição permanece confirmada mesmo quando a entrega posterior falhar.
- A implementação de notifications fica pendente de uma decisão técnica de Lucas.

## Alternativas consideradas

- Fazer o Workflow enviar diretamente: rejeitado porque acoplaria o pacote a um transporte e modelo de usuários.
- Persistir snapshot dos membros: rejeitado porque adicionaria serialização e regras de consistência sem necessidade identificada.
- Fixar agora evento, payload, fila ou Job: rejeitado porque esses detalhes foram explicitamente delegados ao responsável técnico.
- Transportar models completos, request ou payload do Forms: rejeitado porque aumentaria o acoplamento e o risco de serializar estado inconsistente ou sensível.

## Histórico consolidado

Este ADR consolida os arquivos anteriormente nomeados `0012-entrega-de-notifications-por-evento.md`, `0013-fila-database-para-notifications.md`, `0014-identificadores-de-usuarios-em-notifications.md`, `0015-falha-explicita-para-usuarios-nao-resolvidos.md`, `0017-conteudo-de-notifications-no-consumidor.md`, `0020-payload-minimo-do-evento-de-notification.md`, `0025-workflow-publica-roles-consumidor-resolve-membros.md`, `0026-resolucao-de-membros-no-processamento.md` e `0029-detalhes-de-implementacao-delegados-ao-lucas.md`. As propostas anteriores de evento, fila `database`, `ShouldQueue`, identificadores de usuários, validação de e-mails e payload mínimo ficam registradas como direcionamentos preliminares; a decisão vigente é deixar a definição técnica para Lucas.
