# Especificação — Refactor mínimo do Workflow V2 para o Equivalencia

## Problem Statement

O Workflow está sendo desenvolvido na branch `refactor`, mas a implementação atual possui incompatibilidades entre migrations, models, DTOs, services, definição de workflow, integração com Forms V2 e documentação de contrato. O Equivalencia precisa somente do fluxo mínimo de aproveitamentos; não está em uso, em produção, nenhuma funcionalidade operacional de Forms ou Workflow que exija preservar essas tabelas.

O objetivo não é reescrever ou modernizar todo o pacote. É corrigir o menor conjunto de problemas que impeça o funcionamento previsto pelo Equivalencia e registrar as decisões novas sem alterar as documentações existentes do Workflow.

O Workflow também precisa permanecer independente do banco de Forms, do mecanismo de roles do consumidor e de detalhes prematuros de notifications. Essas fronteiras devem ser claras para evitar que o refactor resolva um problema criando novos acoplamentos.

## Solution

Manter a branch `refactor` e realizar apenas as correções necessárias ao fluxo mínimo do Equivalencia:

1. sincronizar a definição `equivalencia` como executável;
2. criar ou localizar de forma idempotente um WorkflowObject para um Aproveitamento;
3. iniciar em `aluno_inicio`;
4. executar `tr_inicio_conferencia` sem formulário;
5. executar `tr_conferencia_indica` com o formulário `obs`;
6. persistir o histórico de transições no schema V2;
7. resolver roles por uma integração fornecida pelo consumidor;
8. tratar o contrato funcional mínimo de notifications, deixando a implementação técnica para Lucas.

O pacote será o único dono das próprias tabelas e migrations. O contrato completo, as divergências com a documentação existente e os detalhes de integração devem ser registrados em novos documentos; os documentos existentes do Workflow não serão alterados.

## User Stories

1. Como operador, quero sincronizar a definição `equivalencia`, para disponibilizar o fluxo usado pelo Equivalencia.

2. Como operador, quero acionar o serviço de sincronização pelo comando administrativo `php artisan workflow:sync`, para carregar e validar as definições antes do uso.

3. Como operador, quero que uma definição sincronizada seja `published`, para que o fluxo seja executável imediatamente.

4. Como operador, quero que `draft` não seja aceito em uma definição executável, para evitar uma publicação parcial ou ambígua.

5. Como operador, quero que o sync valide todas as definições e referências antes de persistir, para impedir estado parcial.

6. Como operador, quero receber os erros de uma execução de sync de forma agrupada, para corrigir várias inconsistências em uma única rodada.

7. Como operador, quero que uma referência a place, transição, formulário ou role inexistente cause erro explícito, para não publicar um fluxo que falhará mais tarde.

8. Como consumidor, quero fornecer o resolvedor de roles, para que o Workflow não conheça tabelas ou APIs específicas de autorização.

9. Como consumidor, quero que uma role inexistente bloqueie o sync, para garantir que o workflow publicado tenha referências autorizáveis.

10. Como consumidor, quero que uma role existente sem membros não invalide a definição, para separar validade da configuração e disponibilidade momentânea de destinatários.

11. Como consumidor, quero que a ausência de roles não impeça uma transição válida, para não criar envio ou fila quando não há destinatários.

12. Como usuário do Equivalencia, quero criar ou localizar um WorkflowObject por tipo e identificador do Aproveitamento, para que a operação seja idempotente.

13. Como usuário do Equivalencia, quero iniciar o objeto em `aluno_inicio`, para refletir o ponto inicial do fluxo.

14. Como usuário do Equivalencia, quero executar `tr_inicio_conferencia` sem formulário, para encaminhar o aproveitamento a `svgrad_conferencia` sem dados adicionais.

15. Como usuário do Equivalencia, quero executar `tr_conferencia_indica` com `obs`, para registrar a observação e encaminhar o aproveitamento a `depto_indica_docente`.

16. Como usuário, quero consultar o estado atual, para saber em que etapa o aproveitamento está.

17. Como usuário, quero consultar as transições disponíveis, para saber quais ações são permitidas no estado atual.

18. Como auditor, quero consultar o histórico append-only, para reconstruir a sequência de transições realizadas.

19. Como auditor, quero que o histórico não copie o payload do Forms, para manter uma única fonte de verdade para os dados do formulário.

20. Como responsável pelo schema, quero que Workflow não dependa por foreign key de Forms ou usuários, para manter os pacotes desacoplados.

21. Como responsável pelo schema, quero que Workflow seja dono de suas tabelas, para que o Equivalencia não mantenha migrations concorrentes.

22. Como responsável pelo ciclo de vida, quero impedir a remoção de um objeto que possua histórico, para preservar a trilha de auditoria.

23. Como responsável por notifications, quero usar as roles dos destinos como destinatários padrão, para manter comportamento previsível.

24. Como responsável por notifications, quero usar `append_roles` para acrescentar destinatários, para cobrir o caso simples sem regra de substituição.

25. Como responsável pelo contrato, quero rejeitar `override_roles`, `users` e `emails`, para evitar funcionalidades complexas e não suportadas no V2.

26. Como responsável pelo contrato, quero que uma role válida sem membros não provoque retry ou rollback da transição, para que a transição permaneça válida mesmo sem destinatários atuais.

27. Como responsável pela implementação, quero que Lucas defina eventos, listeners, Mailables, filas, retries e tratamento de falhas, para aproveitar sua experiência e não cristalizar detalhes prematuramente.

28. Como mantenedor, quero corrigir apenas as incompatibilidades que bloqueiam o fluxo do Equivalencia, para não ampliar o refactor sem retorno.

29. Como mantenedor, quero preservar as documentações existentes do Workflow, para manter o histórico documental e registrar o novo contrato em documentos separados.

## Implementation Decisions

- A branch `refactor` permanece como base. O escopo é o fluxo mínimo necessário ao Equivalencia; modernizações gerais, limpeza estética e funcionalidades sem impacto nesse fluxo são secundárias e ficam fora.

- A definição `equivalencia` será sincronizada como `published`. `draft` não é permitido em nenhum momento para uma definição que possa ser executada pelo consumidor.

- O serviço de sincronização é uma ferramenta administrativa acionada por `php artisan workflow:sync`; usuários finais não o invocam durante o uso normal. Na execução da aplicação, o sistema consome definições já sincronizadas e publicadas.

- O sync lê e valida todas as entradas, agrega os erros e só persiste quando a coleção inteira estiver válida. A validação inclui estados, places, transições, referências de Forms, roles e consistência do grafo.

- A definição publicada deve ser válida para o contrato V2, incluindo a estrutura de `initial_places`, listas de places e transições, `from` e `tos`. O código não deve tratar listas como mapas sem uma resolução explícita por nome.

- A integração usa o schema V2 instalado: `object_type`, `object_id` e `current_places` para o objeto; o histórico deve usar os nomes e colunas efetivamente definidos pela migration do pacote.

- O WorkflowObject é associado ao objeto de negócio por tipo e identificador e deve ser localizado antes de criar outro. O comportamento precisa ser idempotente para o mesmo Aproveitamento.

- No fluxo mínimo, a primeira place é `aluno_inicio`. `tr_inicio_conferencia` conduz a `svgrad_conferencia` e não possui formulário. `tr_conferencia_indica` conduz a `depto_indica_docente` e referencia o formulário `obs`.

- Formulário omitido, nulo ou explicitamente falso significa ausência de formulário. Nessa situação, não se deve renderizar, validar ou criar submissão artificial. Quando houver nome, o Workflow resolve o formulário por meio da API pública do Forms V2.

- O histórico é append-only e registra a transição e seus metadados próprios. O Workflow não copia o payload da submissão do Forms. Um objeto com histórico não deve ser removido em cascata.

- Referências opcionais a submissões de Forms e usuários não terão foreign keys externas. O desacoplamento vale para o schema e não elimina a possibilidade de o consumidor fornecer identificadores como metadados.

- Workflow não é dono de roles, usuários, e-mails ou permissões. Não serão mantidas tabelas próprias de associação entre roles e usuários no pacote. O consumidor fornece o resolvedor de existência e, quando necessário, resolve os membros no momento do processamento.

- Role inexistente é erro de validação e impede o sync. Role existente sem membros é configuração válida e não gera envio, retry ou rollback por si só. Uma definição sem roles não gera evento de notification.

- O contrato funcional de notifications aceita as roles padrão derivadas dos destinos e `append_roles`. `override_roles`, `users` e `emails` não fazem parte do V2 e devem produzir erro explícito durante a validação.

- `notifications` é um único objeto opcional por transição, não uma lista de objetos. Quando ausente, não há configuração adicional de destinatários; as roles padrão vêm dos destinos `tos` e `append_roles` apenas acrescenta roles.

- O Workflow V2 não deduplica roles ou destinatários. Repetições resultantes da configuração ou de múltiplas origens não serão tratadas por uma etapa adicional de normalização.

- Notifications faz parte do escopo funcional, mas os detalhes técnicos de evento, listener, Mailable, fila, serialização, retry, falha e operação do worker serão definidos por Lucas em documentação posterior do Workflow. Esta especificação não autoriza implementar esses detalhes agora.

- As migrations e tabelas de Workflow pertencem ao pacote Workflow. O Equivalencia não manterá cópias concorrentes. Migrations históricas do Equivalencia não devem ser alteradas; a regeneração autorizada das tabelas é uma decisão de implantação documentada no repositório Equivalencia.

- Migrations novas do Workflow V2 podem ser ajustadas antes da implantação. O `WorkflowServiceProvider` deve carregar diretamente as migrations do pacote; migrations históricas não serão alteradas nem recarregadas.

- As documentações existentes do Workflow não serão editadas. Esta especificação e os demais documentos novos registram o contrato V2, as divergências e as decisões que forem necessárias para o Equivalencia.

## Testing Decisions

- O principal ponto de validação será a integração real com o Equivalencia e a cópia local recente do banco de produção, usando o pacote Workflow real e a definição real `equivalencia`. Testes unitários de DTOs ou mocks isolados não substituem esse cenário.

- Testar sync válido e confirmar que a definição fica executável como `published`.

- Testar sync inválido com múltiplos erros e confirmar que nenhuma definição é persistida parcialmente.

- Testar rejeição de `draft`, place inexistente, transição inválida, formulário inexistente e role inexistente.

- Testar a resolução de listas de places e transições e confirmar que `initial_places`, `from` e `tos` são interpretados conforme o contrato V2.

- Testar criação e localização idempotente do WorkflowObject, leitura de estado e transições disponíveis.

- Testar o fluxo mínimo completo: `aluno_inicio` → `tr_inicio_conferencia` sem formulário → `svgrad_conferencia` → `tr_conferencia_indica` com `obs` → `depto_indica_docente`.

- Testar que a transição sem formulário não chama o Forms e que a transição com `obs` usa a API pública do Forms V2 e registra a submissão correspondente.

- Testar histórico append-only, consulta da sequência e proteção contra remoção de objeto com histórico.

- Testar referências sem foreign key externa e confirmar que a remoção/regeneração de tabelas de Forms não quebra o schema do Workflow.

- Testar roles: inexistente bloqueia sync; existente sem membros permanece válida sem envio; ausência total de roles não cria notification.

- Testar parsing e validação funcional de `tos` e `append_roles`, além da rejeição explícita de `override_roles`, `users` e `emails`.

- A entrega de notifications, o worker, retries e falhas serão testados somente após Lucas definir a implementação técnica.

## Out of Scope

- Reescrita completa ou modernização geral do Workflow.
- Alteração das documentações existentes do Workflow.
- Suporte a `override_roles`, `users` ou `emails` em notifications V2.
- Definição ou implementação técnica de eventos, listeners, Mailables, Jobs, filas, retries e tratamento operacional de notifications.
- Criação de tabelas de roles, usuários ou e-mails no Workflow.
- Acoplamento por foreign key entre Workflow, Forms e usuários.
- Migração ou backfill de dados legados de Forms/Workflow nesta implantação.
- `migrate:fresh` em produção.
- Alterações de código nesta etapa documental.

## Further Notes

- Os problemas conhecidos da branch `refactor` devem ser tratados explicitamente quando bloquearem o fluxo: consulta de `is_published` em vez de `status`; uso de `model_type`, `model_id` e `current_place` em vez de `object_type`, `object_id` e `current_places`; leitura de `initial_marking` em vez de `initial_places`; namespace e defaults ausentes nos DTOs; interpretação de listas de places e transições como mapas; `to` em vez de `tos`; variável de formulário não inicializada na transição sem formulário; divergência entre nomes do model e da migration de histórico; e uso da API antiga do Forms em vez da API V2.

- A documentação técnica existente continua sendo referência histórica. Quando houver divergência necessária para o contrato V2 do Equivalencia, a decisão deve ser registrada em documento novo, sem editar o arquivo antigo.

- A especificação de migração e integração do Equivalencia é o documento que coordena a implantação entre repositórios. Este documento define o que o pacote Workflow deve oferecer ao consumidor.

- A seleção, inclusão e commit do documento ficam sob responsabilidade do desenvolvedor do repositório Workflow.
