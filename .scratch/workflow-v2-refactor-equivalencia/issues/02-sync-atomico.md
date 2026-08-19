# 02 — Sincronizar definições de Workflow de forma atômica

**What to build:** Um operador consegue sincronizar a definição `equivalencia` como executável, recebendo todos os erros de uma execução sem deixar o banco parcialmente sincronizado.

**Blocked by:** Workflow 01 — Alinhar schema, migrations e histórico do Workflow V2.

**Status:** ready-for-agent

- [ ] O serviço de sync valida todas as definições, versões, places, transições, referências de Forms e roles antes de persistir.
- [ ] Erros encontrados na mesma execução são agregados e apresentados juntos.
- [ ] Qualquer erro impede todas as alterações daquela execução.
- [ ] A definição executável é sincronizada como `published`.
- [ ] `draft` não é aceito para uma definição que possa ser executada.
- [ ] O formato de `initial_places`, `from` e `tos` é interpretado conforme o contrato V2.
- [ ] Role inexistente, resolver ausente ou falha do resolver impedem o sync com erro explícito.
- [ ] Testes comprovam sync válido, falha agrupada e ausência de persistência parcial.
