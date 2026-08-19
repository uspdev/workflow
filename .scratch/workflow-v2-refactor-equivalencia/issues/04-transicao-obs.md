# 04 — Executar a transição com o formulário obs

**What to build:** O usuário consegue executar a indicação de docente com o formulário `obs`, usando a API pública do Forms V2 e mantendo a submissão relacionada ao histórico do Workflow.

**Blocked by:** Forms 02 — Disponibilizar a API pública de consumo do Forms V2; Workflow 03 — Executar o fluxo mínimo sem formulário.

**Status:** ready-for-agent

- [ ] A transição `tr_conferencia_indica` resolve o formulário `obs` pelo contrato público do Forms V2.
- [ ] Os dados são validados e submetidos pela API pública do Forms.
- [ ] A submissão fica vinculada à versão efetivamente utilizada.
- [ ] A transição leva o objeto de `svgrad_conferencia` a `depto_indica_docente`.
- [ ] O histórico guarda a referência e os metadados da transição sem duplicar o payload do formulário.
- [ ] Formulário inexistente ou dados inválidos produzem erro sem concluir a transição.
- [ ] Testes comprovam a transição com `obs` e a distinção entre transições com e sem formulário.
