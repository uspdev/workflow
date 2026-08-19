# Plano de implementação em ondas — consolidação do Workflow após o plano 1.1

Status: proposto — aguardando aprovação e definição de responsáveis  
Data: 2026-08-18  
Base técnica avaliada: branch `refactor`, commit `081817e`

## 1. Objetivo

Concluir as correções relevantes do pacote `uspdev/workflow` que ficaram
deliberadamente fora do fluxo mínimo do Equivalencia no plano 1.1.

O plano anterior entregou o núcleo necessário para sincronizar a definição
`equivalencia`, iniciar um objeto, executar as duas primeiras transições,
integrar o formulário `obs`, persistir histórico e validar o contrato funcional
de notifications por roles. Essa entrega permanece válida e não deve ser
reescrita sem necessidade comprovada.

Este novo plano trata o Workflow como um pacote reutilizável e prepara uma
versão segura para uso além daquele caminho essencial. Os objetivos são:

- reduzir a interface pública a contratos coerentes e testáveis;
- impedir que rotas administrativas antigas e incompatíveis sejam expostas por
  padrão nas aplicações consumidoras;
- eliminar a convivência ambígua entre o runtime V2 e a implementação legada;
- tornar autorização, bindings e integrações dependências explícitas do
  consumidor, sem acoplamento a classes da aplicação hospedeira;
- concluir a solução técnica de notifications sem comprometer a transação do
  workflow;
- permitir que o mesmo objeto de domínio participe de workflows diferentes,
  preservando a versão da definição usada em cada processo;
- corrigir empacotamento, configuração, documentação e matriz de testes;
- fornecer uma rota de atualização incremental, observável e reversível.

Este documento é voltado à equipe de manutenção. Ele descreve decisões,
dependências, riscos, critérios de aceite e evidências esperadas. As decisões e
responsabilidades devem ser assumidas e registradas pela equipe.

### 1.1. Relação com o plano 1.1

Este plano começa depois da conclusão de W01 a W05. Ele não substitui os
tickets E02, E03 e E04 do Equivalencia e não autoriza a implantação produtiva
daquela aplicação antes da validação prevista no plano original.

As seguintes entregas são consideradas baseline e devem continuar verdes:

- schema V2 de definições, objetos e histórico;
- sync atômico, idempotente e com validação agrupada;
- runtime mínimo sem formulário;
- transição com o formulário `obs` pela interface pública do Forms V2;
- histórico append-only;
- contrato funcional de notifications por roles;
- 28 testes e 152 asserções existentes no commit de referência.

### 1.2. Acompanhamento objetivo

Legenda: ✅ concluído; 🟡 em andamento; ⬜ não iniciado; ⛔ bloqueado.

| Ticket | Status | Resultado esperado |
| --- | --- | --- |
| C01 | ⬜ Não iniciado | Superfície HTTP legada contida, protegida e desabilitada por padrão. |
| C02 | ⬜ Não iniciado | Interface pública única para o runtime V2 e erros tipados. |
| C03 | ⬜ Não iniciado | Ciclo de vida de definições centralizado e concorrência de publicação resolvida. |
| C04 | ⬜ Não iniciado | Autorização desacoplada de `App\\Models\\User`, Spatie e helpers globais. |
| C05 | ⬜ Não iniciado | Bindings usam resolvers registrados e falham de forma segura. |
| C06 | ⬜ Não iniciado | Identidade do processo permite workflows diferentes para o mesmo objeto. |
| C07 | ⬜ Não iniciado | Decisão técnica de notifications aprovada e documentada. |
| C08 | ⬜ Não iniciado | Notifications produzidas após confirmação da transição, com entrega observável. |
| C09 | ⬜ Não iniciado | Funcionalidades administrativas legadas migradas ou removidas conscientemente. |
| C10 | ⬜ Não iniciado | Pacote, dependências, configuração e instalação coerentes. |
| C11 | ⬜ Não iniciado | Documentação pública e guia de atualização correspondem ao V2 consolidado. |
| C12 | ⬜ Não iniciado | Matriz de testes, segurança, concorrência e integração ampliada. |
| C13 | ⬜ Não iniciado | Atualização validada em aplicação de referência e release preparada. |

Resumo das ondas: todas as ondas deste plano ainda não foram iniciadas.

## 2. Diagnóstico que fundamenta o plano

Os problemas abaixo não invalidam os tickets W01 a W05. Eles demonstram que a
parte não contemplada pelo fluxo essencial ainda não pode ser considerada uma
interface suportada do pacote.

| Prioridade | Problema observado | Consequência |
| --- | --- | --- |
| Crítica | O provider carrega automaticamente rotas administrativas protegidas apenas pelo middleware `web`. | Aplicações consumidoras recebem uma superfície administrativa sem autenticação ou autorização obrigatória. |
| Crítica | Publicação, mudança para draft, geração, restauração e remoção de backups usam requisições `GET`. | Operações mutáveis ficam sujeitas a acionamento indevido e não seguem as garantias esperadas de CSRF e semântica HTTP. |
| Alta | Controllers e views chamam métodos removidos ou renomeados e ainda interpretam o formato V1. | Diversas rotas terminam em erro ou manipulam incorretamente definições e estados V2. |
| Alta | A classe estática legada, os models e a facade apresentam interfaces concorrentes. | O consumidor não sabe qual contrato é estável; correções precisam ser repetidas em caminhos diferentes. |
| Alta | Bindings aceitam qualquer nome de resolver e resolvers desconhecidos devolvem o valor bruto. | Erros de configuração podem persistir dados incorretos sem falha explícita. |
| Alta | O registro de resolvers do provider não é o mesmo usado pelo runtime. | Um resolver aparentemente registrado pode nunca ser executado. |
| Alta | A entrega técnica de notifications ainda não existe. | O contrato calcula roles, mas nenhuma mensagem ou trabalho de entrega é produzido. |
| Média | A identidade de `workflow_objects` considera apenas `object_type` e `object_id`. | Um objeto de domínio não pode participar de workflows diferentes e uma nova inicialização pode devolver o processo errado. |
| Média | O campo declarativo `supports` não é imposto pelo runtime. | Uma definição pode ser iniciada para um tipo de objeto que ela não declara suportar. |
| Média | Código do pacote referencia `App\\Models\\User`, Spatie Permission, Symfony Workflow e USP Theme sem contrato ou dependência coerente. | Instalações mínimas podem falhar somente quando caminhos pouco testados forem acionados. |
| Média | O caminho de publicação da configuração não corresponde ao arquivo existente e o README descreve tags e métodos inexistentes. | Instalação e manutenção são induzidas a procedimentos incorretos. |
| Média | Os testes atuais cobrem bem o fluxo mínimo, mas não cobrem rotas, UI, backups, instalação mínima, concorrência real ou atualização de schema. | A suíte verde não representa toda a superfície carregada pelo pacote. |

Arquivos centrais desse diagnóstico:

- `src/WorkflowServiceProvider.php`;
- `routes/web.php`;
- `src/Http/Controllers/WorkflowController.php`;
- `src/Http/Controllers/WorkflowBackupController.php`;
- `src/Workflow.php`;
- `src/WorkflowService.php`;
- `src/Models/WorkflowDefinition.php`;
- `src/Models/WorkflowObject.php`;
- `src/Services/WorkflowSyncService.php`;
- `composer.json`;
- `README.md`.

## 3. Princípios obrigatórios

### 3.1. Preservação do comportamento já entregue

- O fluxo mínimo do Equivalencia deve permanecer funcional durante todas as
  ondas.
- Uma mudança estrutural deve ser acompanhada por teste de regressão no mesmo
  commit.
- O histórico continua append-only e nunca deve copiar o payload completo de
  uma submissão do Forms.
- Objetos existentes continuam vinculados à versão da definição com a qual
  foram iniciados.
- Não se deve reintroduzir foreign keys externas para Forms ou usuários.

### 3.2. Interface pública e profundidade do módulo

O runtime V2 deve ser tratado como um módulo profundo: uma interface pequena
deve esconder transação, bloqueio concorrente, Forms, autorização, bindings,
histórico e produção de notifications.

Para o consumidor, as operações essenciais devem se concentrar em:

1. sincronizar ou consultar definições;
2. iniciar ou localizar um processo;
3. consultar estado, ações e histórico;
4. aplicar uma transição;
5. interpretar um resultado ou uma falha tipada.

Models Eloquent podem continuar oferecendo navegação e leitura conveniente,
mas não devem ser a localização principal da orquestração. Controllers, views
e comandos devem atravessar a mesma interface usada pelos demais consumidores.

### 3.3. Dependências do consumidor

- Workflow não é dono de usuários, roles, permissões, e-mails, conteúdo ou
  canais de entrega.
- Autorização deve entrar por um adapter fornecido pelo consumidor.
- Resolvers de bindings devem ser registrados explicitamente e validados antes
  que uma definição seja publicada.
- Forms deve ser acessado somente pela interface pública do Forms V2.
- Dependências opcionais de UI não podem ser necessárias para instalar ou usar
  o runtime.

### 3.4. Banco e migrations

- Migrations já publicadas não devem ser editadas.
- Toda evolução deve usar migration incremental com nome novo.
- Não se deve usar `migrate:fresh` como estratégia de atualização.
- Antes de mudar uma restrição de unicidade, deve existir diagnóstico e
  tratamento determinístico dos dados atuais.
- O `down()` de uma migration não pode apagar dados que não possam ser
  reconstruídos, salvo decisão explícita e documentada.

### 3.5. Compatibilidade e remoção de legado

- Compatibilidade não significa manter silenciosamente uma interface quebrada.
- APIs antigas usadas por consumidores conhecidos devem receber aviso de
  depreciação e uma janela de migração.
- Código sem consumidor conhecido, sem testes e incompatível com o V2 deve ser
  removido em vez de receber camadas adicionais.
- Toda remoção pública deve respeitar versionamento semântico e aparecer no
  guia de atualização.

### 3.6. Segurança operacional

- Nenhuma rota mutável pode usar `GET`.
- Rotas administrativas exigem autenticação e autorização explícitas.
- O módulo HTTP deve permanecer desabilitado por padrão até estar integralmente
  compatível com o V2.
- Nomes vindos de definições não podem ser usados diretamente para formar
  caminhos de arquivo.
- Backup, restore e sync devem operar somente dentro de diretórios permitidos,
  com nome de arquivo normalizado e validação de conteúdo.

## 4. Tickets abrangidos

### Núcleo, segurança e interface

| ID | Ticket | Responsabilidade |
| --- | --- | --- |
| C01 | Conter a superfície HTTP legada | Remover a exposição automática e corrigir riscos imediatos de autorização e verbos HTTP. |
| C02 | Consolidar a interface pública do runtime | Criar um único caminho suportado para iniciar, consultar e transicionar. |
| C03 | Centralizar o ciclo de vida das definições | Unificar sync, publicação, versionamento e invariantes. |
| C04 | Criar a seam de autorização do consumidor | Substituir dependências diretas de usuário, Gate e Spatie. |
| C05 | Tornar bindings fail-closed | Unificar registro, validação e execução dos resolvers. |
| C06 | Corrigir identidade e compatibilidade do objeto | Separar identidade do workflow, versão e objeto de domínio. |

### Notifications e administração

| ID | Ticket | Responsabilidade |
| --- | --- | --- |
| C07 | Decidir a arquitetura técnica de notifications | Aprovar persistência, momento de produção, payload e responsabilidades. |
| C08 | Implementar produção e entrega de notifications | Materializar a decisão de C07 com idempotência e observabilidade. |
| C09 | Resolver o destino da administração legada | Migrar somente o que tiver uso confirmado e remover o restante. |

### Distribuição, documentação e release

| ID | Ticket | Responsabilidade |
| --- | --- | --- |
| C10 | Corrigir o empacotamento | Declarar dependências, configuração, recursos opcionais e versões suportadas. |
| C11 | Reescrever a documentação pública | Documentar apenas interfaces reais e fornecer migração do legado. |
| C12 | Ampliar a estratégia de testes | Cobrir instalação, segurança, concorrência, banco e integrações. |
| C13 | Validar atualização e preparar release | Executar a atualização em aplicação de referência e produzir evidências. |

## 5. Grafo de dependências

```text
C01 ────────────────────────────┐
                               │
C02 ──> C03 ──> C06 ───────────┼──> C09 ──> C10 ──> C11 ──┐
  │       │                    │                │          │
  └──> C04 ────────────────────┘                │          ├──> C13
          │                                     │          │
          └──────────────┐                      └──> C12 ──┘
                         │                           ▲
C03 ──> C05 ─────────────┤                           │
                         ├───────────────────────────┤
W05 ──> C07 ──> C08 ─────┤                           │
                         │                           │
                 C03, C06 e C09 ────────────────────┘
```

C01 pode começar imediatamente. C02 e C10 também podem avançar em paralelo,
desde que a equipe congele a criação de novas interfaces públicas até a
conclusão de C02.

Dependências formais:

| Ticket | Depende de |
| --- | --- |
| C01 | Nenhum ticket deste plano. |
| C02 | Nenhum ticket deste plano. |
| C03 | C02. |
| C04 | C02. |
| C05 | C02 e C03. |
| C06 | C02 e C03. |
| C07 | Contrato funcional W05 e participação de Lucas; pode avançar em paralelo a C02. |
| C08 | C02 e C07. |
| C09 | C01, C02, C03, C04 e C06. |
| C10 | Pode iniciar imediatamente, mas só pode ser concluído após as decisões de C09 sobre funcionalidades opcionais. |
| C11 | C09 e C10. |
| C12 | C03, C04, C05, C06, C08, C09 e C10. |
| C13 | C11 e C12. |

## 6. Ondas de execução

### Onda 0 — Contenção e baseline

**Status:** ⬜ Não iniciada.  
**Ticket:** C01.

Objetivo: reduzir imediatamente a superfície de risco sem mudar o fluxo mínimo
do Equivalencia.

#### C01 — Conter a superfície HTTP legada

O trabalho deve:

- introduzir uma configuração explícita para habilitar as rotas administrativas,
  com valor padrão `false`;
- deixar de registrar `routes/web.php` quando a funcionalidade estiver
  desabilitada;
- exigir middleware configurável de autenticação e um gate administrativo em
  todas as rotas quando o módulo for habilitado;
- substituir por `POST`, `PUT`, `PATCH` ou `DELETE` todas as operações mutáveis
  expostas como `GET`;
- impedir criação, publicação, alteração ou remoção de definição fora do ciclo
  de validação V2;
- restringir backup e restore ao diretório configurado, usando nomes
  normalizados e impedindo traversal;
- corrigir a inconsistência entre nomes de arquivos gerados, listados,
  restaurados e removidos;
- adicionar testes que comprovem que nenhuma rota é registrada por padrão;
- adicionar testes de autenticação, autorização, CSRF, verbos HTTP e acesso a
  arquivos quando o módulo for habilitado.

Saída obrigatória:

- risco imediato contido;
- configuração documentada;
- inventário das rotas existentes e decisão provisória de manter ou remover
  cada uma em C09.

Critério de passagem: nenhuma aplicação que instale o pacote deve receber a UI
administrativa sem habilitação consciente.

### Onda 1 — Interface única e ciclo de vida

**Status:** ⬜ Não iniciada.  
**Tickets:** C02 e C03, em sequência.

Objetivo: criar uma única interface suportada antes de migrar funcionalidades
secundárias.

C03 começa depois que a interface de C02 estiver aprovada. C10 e a decisão C07
podem continuar em paralelo a esta onda.

#### C02 — Consolidar a interface pública do runtime

O trabalho deve:

- definir `WorkflowService` — ou um sucessor com nome aprovado — como a entrada
  pública do runtime;
- concentrar nessa implementação a transação, o lock do objeto, a validação da
  transição, autorização, integração com Forms, bindings, estado e histórico;
- manter a facade apenas como acesso conveniente à mesma implementação;
- retornar um resultado de transição que permita ao consumidor identificar o
  objeto atualizado, o histórico criado e a submissão relacionada, sem expor
  models internos desnecessários;
- manter exceções distintas para definição ausente, transição inexistente,
  transição indisponível, acesso negado, formulário inválido, binding inválido
  e falha de integração;
- preservar `WorkflowObject::apply()` apenas como delegação temporária, se
  necessário para compatibilidade, com depreciação documentada;
- retirar dos models a criação direta de requests, resolução de dependências do
  container e demais detalhes de orquestração;
- mapear cada método público da classe estática legada para uma destas decisões:
  migrar, adaptar temporariamente, depreciar ou remover;
- não criar duas implementações para a mesma transição.

Testes obrigatórios:

- todos os cenários W03 e W04 pela nova interface;
- retorno e falhas tipadas;
- rollback integral quando Forms, autorização ou binding falhar;
- duas tentativas concorrentes da mesma transição;
- compatibilidade temporária dos métodos declarados como suportados.

Critério de passagem: controllers, comandos, facade e consumidores conseguem
usar o mesmo módulo, e a lógica de transição existe em um único lugar.

#### C03 — Centralizar o ciclo de vida das definições

O trabalho deve:

- fazer sync, publicação, arquivamento e consulta compartilharem as mesmas
  validações e invariantes;
- impedir que `storeDefinition()`, `publish()` ou qualquer tela persista uma
  definição que o sync rejeitaria;
- garantir, inclusive sob concorrência, no máximo uma versão publicada por
  nome;
- definir claramente quando uma versão explícita draft ou archived pode ser
  consultada e impedir seu uso acidental para iniciar processos;
- impedir alterações destrutivas em uma definição já usada por objetos;
- manter a definição e a versão originais nos processos em andamento após a
  publicação de uma versão nova;
- substituir acessos diretos ao model por operações do módulo de definições;
- registrar decisões de concorrência e publicação em ADR.

Testes obrigatórios:

- publicação concorrente;
- ressincronização idempotente;
- atualização inválida sem persistência parcial;
- tentativa de excluir ou alterar versão em uso;
- processo antigo preservado depois da publicação de versão nova.

Critério de passagem: não existe caminho suportado capaz de contornar a
validação V2 ou criar duas versões publicadas do mesmo workflow.

### Onda 2 — Adapters do consumidor e identidade

**Status:** ⬜ Não iniciada.  
**Tickets:** C04, C05 e C06, respeitando as dependências formais.

Objetivo: remover acoplamentos da aplicação hospedeira e corrigir limitações do
runtime genérico.

#### C04 — Criar a seam de autorização do consumidor

O trabalho deve:

- definir uma interface de autorização capaz de responder se um ator pode
  executar uma transição que exige determinadas roles;
- fornecer um adapter neutro para workflows sem roles e um adapter de teste;
- deixar adapters de Spatie Permission, Gate ou regras institucionais no
  consumidor ou em integração opcional claramente declarada;
- remover referências diretas a `App\\Models\\User` do núcleo;
- deixar de exigir que o ator herde de uma classe concreta do Laravel;
- separar validação da existência de roles durante o sync da autorização do
  ator durante a execução;
- preservar a regra de que uma transição sem roles não exige autorização por
  role, sem dispensar políticas adicionais do consumidor quando configuradas;
- retornar falha de acesso tipada, sem mascará-la como transição inexistente.

Critério de passagem: o runtime funciona em uma aplicação mínima sem Spatie e
uma aplicação pode fornecer seu adapter sem alterar código do pacote.

#### C05 — Tornar bindings fail-closed

O trabalho deve:

- definir um único registro de resolvers usado pelo sync e pelo runtime;
- rejeitar durante o sync todo resolver não registrado;
- remover o fallback que devolve silenciosamente o valor bruto;
- tratar `direct` como resolver explícito, não como comportamento implícito;
- definir o contrato de entrada, saída, valor ausente e falha de cada resolver;
- impedir que um binding parcialmente executado deixe variáveis ou o objeto de
  domínio alterados;
- decidir e documentar se bindings escrevem somente em `variables` ou também
  no objeto de domínio; até essa decisão, não prometer atualização dinâmica do
  model do consumidor;
- oferecer um mecanismo de extensão pelo container sem estado estático global;
- testar múltiplos bindings, valores nulos, caminhos aninhados, resolver
  ausente, exceção e rollback.

Critério de passagem: toda definição publicada pode executar seus bindings e
nenhum resolver desconhecido é aceito ou tratado como sucesso.

#### C06 — Corrigir identidade e compatibilidade do objeto

O trabalho deve:

- distinguir três conceitos: nome estável do workflow, versão da definição e
  identidade do objeto de domínio;
- permitir que o mesmo `object_type` e `object_id` participe de workflows com
  nomes diferentes;
- continuar impedindo duplicidade do mesmo processo para o mesmo workflow;
- avaliar a inclusão de `workflow_name` em `workflow_objects`, mantendo
  `workflow_definition_id` como referência à versão efetivamente usada;
- criar migration incremental, backfill determinístico e nova restrição de
  unicidade;
- diagnosticar inconsistências antes do backfill e bloquear a migration se o
  resultado for ambíguo;
- fazer `start()` localizar processos pelo nome estável do workflow e pelo
  objeto, não apenas pelo objeto;
- validar `supports` usando a representação canônica de morph type definida na
  documentação;
- rejeitar model não persistido e tipo não suportado com erros distintos;
- testar morph map, ids inteiros e strings, duas definições diferentes para o
  mesmo objeto e publicação de nova versão.

Critério de passagem: a identidade do processo é inequívoca, protegida pelo
banco e preservada durante atualizações de versão.

### Onda 3 — Notifications técnicas

**Status:** ⬜ Não iniciada.  
**Tickets:** C07 e C08.

Objetivo: transformar o contrato funcional de W05 em integração operacional,
sem tornar o Workflow dono da entrega.

#### C07 — Decidir a arquitetura técnica de notifications

Esta decisão deve ser conduzida com Lucas e registrada em ADR antes de iniciar
C08.

O ADR deve definir:

1. mecanismo de produção do trabalho: outbox transacional, evento after-commit
   ou alternativa equivalente;
2. garantia de que nenhum trabalho seja visível antes do commit do estado e do
   histórico;
3. comportamento quando a publicação do trabalho falhar;
4. payload versionado, contendo apenas identificadores, nome e versão do
   workflow, transição, objeto, histórico e roles;
5. chave de idempotência;
6. responsabilidade do consumidor por membros, conteúdo, idioma, canais e
   envio;
7. política de retries, descarte, reprocessamento e dead letter;
8. métricas, logs, correlação e dados sensíveis;
9. estratégia de compatibilidade do payload;
10. operação síncrona ou assíncrona e requisitos de worker.

A preferência técnica inicial é uma outbox transacional quando houver exigência
de entrega confiável. Um evento after-commit simples só deve ser escolhido se a
perda entre commit e publicação for um risco aceito e documentado.

Critério de passagem: arquitetura aprovada, responsabilidades assinadas entre
Workflow e consumidor e plano de testes definido.

#### C08 — Implementar produção e entrega de notifications

O trabalho deve:

- produzir trabalho somente quando a lista calculada de roles não estiver
  vazia;
- preservar repetições enquanto o contrato funcional continuar sem
  deduplicação;
- não resolver membros dentro da transação do Workflow;
- não reverter uma transição confirmada por falha posterior de transporte;
- persistir ou publicar o payload aprovado em C07 somente após estado e
  histórico estarem consistentes;
- oferecer ao consumidor um adapter para processar o trabalho;
- aplicar idempotência em reprocessamentos;
- registrar tentativas, sucesso, falha terminal e correlação com o histórico;
- fornecer comando ou procedimento seguro de reprocessamento, se houver
  processamento assíncrono;
- documentar operação, filas e observabilidade.

Testes obrigatórios:

- nenhuma role e role válida sem membros;
- múltiplas roles e repetições;
- rollback da transição antes do commit;
- falha do transportador depois do commit;
- retry e idempotência;
- payload sem models serializados e sem dados do formulário;
- compatibilidade de versão do payload.

Critério de passagem: uma aplicação de referência recebe e processa o trabalho
sem que o Workflow assuma ownership de usuários ou mensagens.

### Onda 4 — Administração e legado

**Status:** ⬜ Não iniciada.  
**Ticket:** C09.

Objetivo: encerrar a convivência indefinida entre V1 e V2.

#### C09 — Migrar ou remover a administração legada

Antes da implementação, a equipe deve identificar consumidores reais de cada
rota, view, método estático, backup e integração com UspTheme.

Para cada funcionalidade, a decisão deve ser uma destas:

- **migrar:** há uso confirmado e valor suficiente para manter;
- **substituir:** a necessidade já é atendida por comando ou interface V2;
- **depreciar:** existe consumidor conhecido que precisa de janela de migração;
- **remover:** não há uso confirmado ou a função é incompatível com o produto.

Funcionalidades mantidas devem:

- usar exclusivamente as interfaces concluídas em C02 e C03;
- interpretar listas e DTOs V2, sem acesso posicional presumido;
- exibir `workflow_history` como fonte do histórico, e não inferi-lo de
  submissões ou activity log;
- usar o Forms V2 apenas por sua interface pública;
- aplicar o adapter de autorização de C04;
- não criar roles ou permissões no banco do consumidor;
- não depender de colunas removidas como `state`, `user_codpes` ou
  `workflow_definition_name`;
- receber testes HTTP e de autorização completos.

Backup e restore, caso permaneçam, devem exportar o envelope completo de
importação — incluindo versão e status permitidos — e reutilizar o mesmo parser
do sync. Graphviz e UspTheme devem ser integrações opcionais, isoladas do
runtime.

Critério de passagem: não permanece código público carregado automaticamente
que dependa do contrato V1 ou chame métodos inexistentes.

### Onda 5 — Pacote e documentação

**Status:** ⬜ Não iniciada.  
**Tickets:** C10 e C11.

#### C10 — Corrigir o empacotamento

O trabalho deve:

- corrigir o caminho e a tag de publicação da configuração;
- decidir se migrations são sempre carregadas pelo provider, publicáveis ou
  ambos, documentando uma única prática recomendada;
- substituir `uspdev/forms: "*"` por uma faixa compatível e testada;
- declarar diretamente toda dependência necessária ao runtime;
- mover dependências exclusivas de UI, Graphviz ou integrações opcionais para
  sugestões, pacotes auxiliares ou instalação opt-in;
- remover imports e providers de dependências ausentes quando a funcionalidade
  correspondente for removida;
- alinhar `minimum-stability`, prefer-stable e constraints de branches com a
  estratégia de release;
- verificar compatibilidade real com as versões declaradas de PHP e Laravel;
- garantir que `composer install --no-dev` forneça tudo que o runtime precisa;
- alinhar a licença informada no Composer e na documentação.

Critério de passagem: instalação mínima reproduzível, sem depender de pacotes
presentes apenas por transitividade ou pelo ambiente de desenvolvimento.

#### C11 — Reescrever a documentação pública

O trabalho deve produzir:

- README curto com instalação, configuração e primeiro fluxo V2 funcional;
- referência da interface pública consolidada;
- especificação do JSON de definição, incluindo `supports`, forms, bindings e
  notifications;
- catálogo de exceções e comportamento transacional;
- guia de autorização e exemplos de adapters;
- guia de notifications para consumidores;
- guia de atualização da interface legada para a V2;
- changelog com remoções e depreciações;
- instruções operacionais de sync, migration, filas e rollback;
- indicação explícita das funcionalidades opcionais de administração.

Exemplos publicados devem fazer parte dos testes ou ser verificados por uma
aplicação-exemplo para evitar nova divergência entre texto e implementação.

Critério de passagem: nenhum exemplo chama método inexistente, usa schema V1
ou instrui a duplicação de migrations no consumidor.

### Onda 6 — Verificação ampliada

**Status:** ⬜ Não iniciada.  
**Ticket:** C12.

#### C12 — Ampliar a estratégia de testes

A suíte deve passar a cobrir a interface realmente distribuída pelo pacote,
não apenas o caminho do Equivalencia.

Cobertura mínima:

- testes unitários de parsing e regras puras;
- testes do módulo de runtime pela interface pública;
- testes de integração com Forms V2 real;
- adapters falsos para autorização, bindings e notifications;
- testes HTTP para toda rota opcional mantida;
- instalação mínima sem Spatie, UspTheme, Graphviz ou classes `App` específicas;
- `composer install --no-dev` e descoberta automática do provider;
- migration incremental com banco preenchido;
- concorrência de start, publicação e apply;
- MySQL ou MariaDB na mesma família usada pela aplicação de referência;
- SQLite apenas como feedback rápido, não como única evidência de banco;
- matriz das versões de PHP e Laravel declaradas em `composer.json`;
- análise estática e padronização automatizada;
- regressão dos 28 testes originais;
- testes de segurança para autorização, CSRF, traversal e operações mutáveis.

Os testes devem observar resultados pela interface do módulo. Testes antigos
que apenas repetem detalhes de implementação devem ser substituídos quando a
nova cobertura estiver presente.

Critério de passagem: CI verde em toda a matriz suportada e falhas conhecidas
registradas sem skips silenciosos.

### Onda 7 — Atualização controlada e release

**Status:** ⬜ Não iniciada.  
**Ticket:** C13.

#### C13 — Validar atualização e preparar release

O trabalho deve usar ao menos uma aplicação de referência com dados
representativos e registrar:

Antes da atualização:

- versão do pacote e commit;
- migrations executadas;
- contagem de definições, objetos e históricos;
- objetos que participam do workflow de referência;
- rotas e integrações opcionais habilitadas;
- dependências e adapters fornecidos pelo consumidor.

Durante a atualização:

- instalação normal pelo Composer;
- execução incremental de migrations;
- backfill de identidade de C06;
- atualização das configurações;
- migração de chamadas depreciadas;
- inicialização de worker ou transportador, se C08 exigir;
- ausência de `migrate:fresh` e de edição manual da tabela `migrations`.

Depois da atualização:

- contagens e amostras preservadas;
- processos antigos continuam na definição original;
- novos processos usam a versão publicada atual;
- o mesmo objeto pode participar de workflows diferentes sem duplicidade
  dentro de cada workflow;
- transições com e sem formulário funcionam;
- autorização usa o adapter do consumidor;
- bindings falham de forma segura;
- notifications são observáveis e reprocessáveis;
- rotas administrativas não aparecem quando desabilitadas;
- não há chamada conhecida à interface removida.

Saída obrigatória:

- relatório reproduzível da atualização;
- lista de breaking changes;
- versão de release compatível com o impacto das remoções;
- plano de rollback do código e das integrações;
- confirmação de que migrations de dados não serão revertidas destrutivamente.

Critério de aprovação: atualização completa sem perda de dados, sem regressão
do fluxo do Equivalencia e sem superfície legada involuntariamente exposta.

## 7. Sequência operacional resumida

1. Aprovar este plano e nomear responsáveis técnicos e revisores.
2. Executar C01 imediatamente para conter as rotas legadas.
3. Congelar novas interfaces públicas até concluir C02.
4. Consolidar runtime e ciclo de vida em C02 e C03.
5. Implementar C04, C05 e C06 sobre a interface consolidada.
6. Conduzir com Lucas a decisão C07 e somente então executar C08.
7. Inventariar consumidores e concluir C09 sem preservar código sem uso
   comprovado.
8. Corrigir distribuição e documentação em C10 e C11.
9. Executar a matriz completa de C12.
10. Validar a atualização e preparar a release em C13.

Ao final de cada ticket, a equipe deve registrar:

- decisão tomada e alternativas rejeitadas;
- arquivos e contratos alterados;
- migrations e impacto sobre dados;
- testes executados e resultado;
- compatibilidade ou breaking changes;
- instruções para a próxima etapa;
- riscos residuais e responsável pelo acompanhamento.

## 8. Condições de bloqueio

O trabalho deve parar e retornar à decisão responsável quando ocorrer qualquer
uma destas situações:

- alteração de migration já publicada em vez de migration incremental;
- necessidade de apagar histórico ou objetos para concluir uma mudança;
- rota administrativa habilitada sem autenticação e autorização;
- manutenção de operação mutável por `GET`;
- persistência de definição por caminho que contorna a validação V2;
- criação de uma segunda implementação da lógica de transição;
- resolver desconhecido tratado como sucesso;
- autorização dependente de uma classe `App` do consumidor;
- notification produzida antes da confirmação da transação;
- falha de entrega capaz de reverter uma transição já confirmada;
- migration de identidade sem diagnóstico de ambiguidades;
- dependência necessária presente apenas por transitividade ou em `require-dev`;
- teste aprovado somente em SQLite quando a mudança depende do comportamento
  do banco de produção;
- remoção de interface pública sem inventário de consumidores e estratégia de
  versão;
- divergência entre documentação, exemplos e código testado.

Um bloqueio não deve ser contornado com acesso direto a models, tabelas ou
internals a partir da aplicação consumidora.

## 9. Riscos e estratégia de mitigação

| Risco | Mitigação |
| --- | --- |
| Desabilitar rotas quebra uma aplicação que usa a UI antiga. | Feature flag temporária, inventário de consumidores e aviso de depreciação. |
| Consolidar a interface muda chamadas existentes. | Adapter temporário, guia de migração e release major quando necessário. |
| Nova identidade encontra dados ambíguos. | Diagnóstico prévio, migration aborta com relatório e correção manual aprovada. |
| Notifications duplicam em retry. | Chave de idempotência e estado observável de processamento. |
| Outbox aumenta operação do consumidor. | Documentar worker, monitoramento, retenção e reprocessamento; só adotá-la após C07. |
| Dependências opcionais deixam de estar disponíveis automaticamente. | Pacotes ou instruções opt-in e detecção com mensagem clara. |
| Refactor do runtime reabre bugs de W03/W04. | Testes pela nova interface antes de remover o caminho anterior. |
| Remoção de legado perde uma necessidade não documentada. | Busca em consumidores, janela de depreciação e aprovação humana registrada. |

## 10. Resultado esperado ao final

Ao concluir C13, devem ser verdadeiras as seguintes condições:

- o pacote expõe uma interface pública pequena e coerente para o runtime V2;
- toda transição usa a mesma implementação transacional;
- definições não podem ser publicadas sem validação completa;
- publicação concorrente não cria mais de uma versão ativa;
- autorização é fornecida pelo consumidor por um adapter explícito;
- bindings usam apenas resolvers registrados e falham de forma segura;
- o mesmo objeto pode participar de workflows diferentes sem perder a
  idempotência dentro de cada workflow;
- `supports` é aplicado pelo runtime;
- notifications são produzidas depois do commit e entregues de forma
  observável conforme a decisão de C07;
- rotas administrativas são opt-in, protegidas e integralmente V2;
- nenhum método público carregado chama implementação inexistente;
- Forms, usuários e roles permanecem desacoplados no schema;
- instalação mínima e `composer install --no-dev` são reproduzíveis;
- README, exemplos, configuração e dependências correspondem ao código;
- migrations são incrementais e preservam definições, objetos e históricos;
- a matriz de CI representa as versões e bancos declarados;
- existe evidência de atualização em aplicação de referência;
- o fluxo mínimo do Equivalencia continua passando sem regressão.

## 11. Definição de concluído do plano

Este plano só pode ser marcado como concluído quando:

1. C01 a C13 estiverem concluídos ou formalmente retirados por decisão
   documentada;
2. todos os critérios de passagem estiverem comprovados;
3. a documentação pública representar a interface distribuída;
4. não houver rota ou integração legada ativa sem testes e ownership;
5. a atualização incremental tiver sido reproduzida;
6. os riscos residuais tiverem responsável e prazo;
7. a versão resultante estiver publicada ou pronta para publicação conforme o
   processo de release do projeto.

Uma suíte unitária verde, isoladamente, não encerra o plano. A conclusão exige
segurança da superfície carregada, consistência de dados, compatibilidade de
instalação e validação em uma aplicação consumidora.
