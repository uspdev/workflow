# ADR 0004 — Sincronização, publicação e validação atômica do Workflow V2

- **Status:** Aceito
- **Data:** 2026-08-14

## Contexto

O Workflow sincroniza definições a partir de arquivos e pode processar várias versões, grafos, referências a Forms e roles em uma mesma execução. Persistir parte das definições antes de encontrar um erro deixaria o banco parcialmente atualizado.

O fluxo do Equivalencia não possui uma etapa administrativa separada para publicar a definição executável. O sync precisa, portanto, produzir uma definição pronta para uso.

## Decisão

Cada execução de `php artisan workflow:sync` seguirá duas fases:

1. ler e validar todas as entradas;
2. persistir somente se nenhuma validação falhar.

Os erros serão acumulados e apresentados em conjunto. A validação abrangerá nomes e versões, estados, consistência do grafo, `initial_places`, places, transições, `from`, `tos`, referências de Forms e roles.

As definições destinadas ao fluxo executável serão sincronizadas como `published`. `draft` não será permitido em nenhuma definição que possa ser executada pelo consumidor. Definições `archived` continuam sujeitas à validação porque podem ser republicadas posteriormente.

Se o resolver de roles do consumidor não estiver disponível ou falhar, o sync falhará fechado e não assumirá que a role existe. Uma role inexistente produzirá erro explícito e impedirá a publicação ou persistência da execução.

Erros do sync não desfazem alterações de execuções anteriores. A atomicidade vale para a execução atual: ou todas as suas definições válidas são persistidas, ou nenhuma alteração daquela execução é feita.

## Consequências

- O banco não recebe um conjunto parcialmente sincronizado.
- Uma única execução retorna vários problemas para correção simultânea.
- O consumidor precisa disponibilizar os resolvers antes do sync.
- A definição `equivalencia` fica executável imediatamente após a sincronização válida.
- A publicação não exige uma etapa administrativa posterior nem permite o estado `draft` para o fluxo em uso.

## Alternativas consideradas

- Persistir cada arquivo imediatamente: rejeitado porque produziria estado parcial.
- Ignorar roles ausentes: rejeitado porque publicaria um workflow que não poderia ser autorizado corretamente.
- Manter `draft` como estado executável: rejeitado porque não há etapa de publicação no fluxo mínimo.
- Fazer rollback de execuções anteriores: rejeitado porque ampliaria o escopo da transação e não é necessário para a atomicidade da execução atual.

## Histórico consolidado

Este ADR consolida o arquivo anteriormente nomeado `0007-sync-atomico-e-validacao-completa.md` e a decisão posterior de que o sync publica a definição executável como `published`.
