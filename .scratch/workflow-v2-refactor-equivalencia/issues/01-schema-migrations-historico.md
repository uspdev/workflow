# 01 — Alinhar schema, migrations e histórico do Workflow V2

**What to build:** O Workflow consegue persistir definições, objetos e histórico usando seu schema V2, com ownership próprio e sem dependências estruturais do banco de Forms ou de usuários.

**Blocked by:** None — can start immediately.

**Status:** ready-for-agent

- [ ] As migrations V2 são carregadas diretamente pelo provider do Workflow.
- [ ] O objeto usa tipo, identificador e places atuais conforme o contrato V2.
- [ ] O histórico usa o contrato efetivo das migrations e mantém referências externas opcionais, sem foreign keys para Forms ou usuários.
- [ ] Foreign keys internas do Workflow continuam funcionando.
- [ ] As tabelas legadas de associação entre Workflow, roles e usuários não são recriadas.
- [ ] O histórico é append-only e um objeto com histórico não pode ser removido em cascata.
- [ ] Testes comprovam o schema e o comportamento de persistência do objeto e do histórico.
