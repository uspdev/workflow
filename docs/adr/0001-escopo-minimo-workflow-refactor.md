---
status: accepted
---

# ADR 0001 — Escopo mínimo do Workflow refactor

## Contexto

O pacote `uspdev/workflow` continuará sendo trabalhado na branch `refactor`. A decisão é corrigir o mínimo necessário para assegurar o funcionamento do Equivalencia e o funcionamento básico do próprio Workflow, sem recomeçar a implementação nem fazer uma revisão geral do pacote.

O consumidor acessa o pacote pelo serviço de Workflow e pelos métodos do objeto de Workflow. APIs estáticas, controllers administrativos legados, Graphviz, telas administrativas e outras funcionalidades que não participam do fluxo mínimo não devem ampliar o refactor.

## Decisão

O escopo desta etapa é o ciclo de integração abaixo:

1. sincronizar a definição `equivalencia`;
2. criar ou localizar de forma idempotente um objeto de Workflow vinculado a um `App\\Models\\Aproveitamento`;
3. iniciar o objeto em `aluno_inicio`;
4. consultar estado atual, ações disponíveis e histórico;
5. aplicar `tr_inicio_conferencia`, uma transição sem formulário, levando o objeto a `svgrad_conferencia`;
6. aplicar `tr_conferencia_indica`, uma transição que usa o formulário `obs` do Forms V2, levando o objeto a `depto_indica_docente`;
7. persistir o novo estado, a submissão do formulário quando aplicável e o histórico da transição.

Podem ser corrigidos problemas de carregamento da definição, interpretação de `initial_places`, `from`, `tos` e `form`, criação e busca do objeto de negócio, leitura e atualização de `current_places`, execução de transições, integração com `obs`, persistência do histórico, autoload, provider e adaptação das respostas pelo consumidor, desde que afetem o ciclo acima.

O serviço de sincronização é uma ferramenta administrativa acionada por `php artisan workflow:sync`. Usuários finais não o invocam durante o uso normal; a aplicação consome definições já sincronizadas e publicadas.

## Fora do escopo

- reescrita completa da API pública;
- migração de controllers administrativos legados;
- ajustes de facade, Graphviz, resolvers ou telas que não participem do ciclo mínimo;
- limpeza estética, reorganização ampla e correção de problemas sem impacto no Equivalencia;
- compatibilidade com formatos históricos não usados pela definição `equivalencia`;
- implementação de funcionalidades novas não necessárias ao fluxo descrito.

Documentações técnicas existentes do pacote, fora do conjunto de ADRs consolidado, não serão alteradas. Divergências novas devem ser registradas em documentos novos.

## Critérios de aceitação

- a definição é sincronizada sem erro e fica disponível para execução;
- o mesmo aproveitamento não gera objetos duplicados;
- o estado inicial é `aluno_inicio`;
- a consulta retorna estado, ações e histórico coerentes;
- `tr_inicio_conferencia` leva o objeto a `svgrad_conferencia` sem usar formulário;
- `tr_conferencia_indica` aceita `obs`, cria a submissão esperada e leva o objeto a `depto_indica_docente`;
- o histórico usa os campos compatíveis com o schema instalado;
- o adapter do Equivalencia converte sucesso e falha sem expor detalhes internos desnecessários.

## Consequências

O pacote continuará contendo problemas conhecidos fora do fluxo mínimo. Eles devem permanecer documentados, mas não devem ampliar esta implementação. Uma nova necessidade funcional exige decisão e critério de aceitação próprios.

## Histórico consolidado

Este ADR consolida o arquivo anteriormente nomeado `0002-escopo-minimo-workflow-refactor.md`, mantendo sua decisão de escopo mínimo e incorporando a regra operacional do serviço de sync.
