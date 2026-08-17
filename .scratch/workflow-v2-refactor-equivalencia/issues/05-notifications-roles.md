# 05 — Aplicar o contrato funcional de notifications por roles

**What to build:** O Workflow valida a configuração funcional de notifications por roles sem assumir ownership de usuários ou detalhes técnicos de entrega.

**Blocked by:** Workflow 02 — Sincronizar definições de Workflow de forma atômica; Workflow 03 — Executar o fluxo mínimo sem formulário.

**Status:** ready-for-agent

- [ ] `notifications` aceita um único objeto opcional por transição.
- [ ] As roles de `tos` são os destinatários padrão e `append_roles` apenas acrescenta roles.
- [ ] `override_roles`, `users` e `emails` são rejeitados explicitamente.
- [ ] Roles inexistentes impedem o sync; roles válidas sem membros não invalidam a transição.
- [ ] A ausência total de roles não gera trabalho de notification.
- [ ] Não há etapa obrigatória de deduplicação.
- [ ] A transição permanece confirmada quando uma role válida não possui membros.
- [ ] A entrega técnica — eventos, listeners, Mailables, filas e retries — continua fora deste ticket e depende da definição posterior de Lucas.
