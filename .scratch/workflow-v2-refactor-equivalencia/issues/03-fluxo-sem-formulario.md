# 03 — Executar o fluxo mínimo sem formulário

**What to build:** O Equivalencia consegue iniciar um processo para um Aproveitamento, consultar seu estado e executar a conferência inicial sem chamar o Forms.

**Blocked by:** Workflow 02 — Sincronizar definições de Workflow de forma atômica.

**Status:** ready-for-agent

- [ ] O mesmo Aproveitamento cria ou localiza um único WorkflowObject.
- [ ] O objeto inicia em `aluno_inicio`.
- [ ] A transição `tr_inicio_conferencia` leva o objeto a `svgrad_conferencia`.
- [ ] Formulário omitido, nulo ou falso não renderiza, valida nem cria submissão.
- [ ] O estado atual, as transições disponíveis e o histórico podem ser consultados.
- [ ] A transição registra histórico compatível com o schema V2.
- [ ] Testes comprovam idempotência, transição sem formulário e leitura do histórico.
