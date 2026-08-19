# Diagnóstico do fluxo mínimo com o Equivalencia

Este documento registra fatos observados durante a validação da branch `refactor` contra o contrato definido em `docs/adr/0001-escopo-minimo-workflow-refactor.md`.
Os demais documentos do pacote não foram alterados.

## Ambiente observado

- pacote Workflow na branch `refactor`;
- pacote Forms V2 instalado por caminho local;
- aplicação consumidora `equivalencia` usando `WorkflowService` por meio de `UspdevWorkflowGateway`;
- banco local carregado a partir de uma cópia recente do banco de produção;
- schema de `workflow_definitions` com `status` e `published_at`;
- schema de `workflow_objects` com `object_type`, `object_id` e `current_places`;
- definição sincronizada com o nome `equivalencia`, versão 1 e status atual `draft`.

## Bloqueios reproduzidos

### 1. Carregamento da definição

`WorkflowService::loadDefinition('equivalencia')` consulta a coluna `is_published`, que não existe no schema atual. A consulta falha com `Unknown column 'is_published'` antes de retornar a definição.

O model `WorkflowDefinition` e a migration usam `status` e `published_at`. O serviço de sincronização também não define o status da definição; por isso, a definição sincronizada pelo seeder permanece como `draft`.

### 2. Criação e localização do objeto

`WorkflowService::start()` e `WorkflowService::find()` usam `model_type`, `model_id` e `current_place`. O schema e o model `WorkflowObject` usam `object_type`, `object_id` e `current_places`.

Enquanto esses nomes não forem alinhados, o serviço não consegue criar nem localizar um objeto associado ao `Aproveitamento`.

### 3. Leitura da definição

`WorkflowDefinitionData` importa `AbstractWfDto` sem namespace, embora a classe esteja em `Uspdev\\Workflow\\Data`. A tentativa de executar `getDefinitionData()` falha com `Class "AbstractWfDto" not found`.

Mesmo depois desse autoload ser corrigido, o DTO valida `initial_places`, mas tenta ler `initial_marking`. A definição usada pelo Equivalencia possui `initial_places` como lista.

Há ainda divergências entre a estrutura em JSON e a validação dos DTOs:

- `from` é string na definição do Equivalencia, enquanto o DTO declara array;
- `tos` é lista de destinos;
- `bindings` e `notifications` são opcionais, mas `TransitionDefinition::fromArray()` acessa os índices sem defaults;
- a validação de transições recebe a coleção inteira de transições onde espera uma transição individual.

### 4. Places e transições

O JSON do Equivalencia usa listas de objetos em `places` e `transitions`. Alguns métodos do model tratam essas listas como mapas indexados pelo nome. Isso afeta a busca de places, transições e rótulos usados na consulta do estado.

O `WorkflowObject::can()` também procura roles como se `places` fosse indexado pelo nome, embora a definição usada seja uma lista.

### 5. Transição sem formulário

`WorkflowObject::apply()` usa o atributo `transition->to`, mas o DTO fornece `transition->tos`. Para uma transição sem formulário, a variável `$form` não é inicializada antes de ser usada ao criar o histórico.

O model e a migration de histórico também não usam o mesmo contrato: o model espera `transition_name` e `from_places`, enquanto a migration efetiva possui `transition` e `from_place`.

### 6. Transição com formulário

`TransitionDefinition::form()` retorna uma instância de `Uspdev\\Forms\\Form`. No Forms V2, essa classe representa a configuração do formulário e não possui os métodos `handleSubmission()` e `generateHtml()` chamados pelo WorkflowObject.

O Forms V2 expõe o fluxo de submissão por `FormsManager`/facade, usando `Request`, definição ativa e `FormSubmission`. Portanto, a transição com `obs` exige uma adaptação explícita no Workflow ou uma chamada coordenada pelo consumidor.

## Itens ainda não classificados como bloqueio

Os seguintes problemas foram identificados, mas não devem ser corrigidos sem demonstrar que afetam o ciclo mínimo:

- API estática legada e controllers administrativos do pacote;
- facade e publicação de configuração;
- Graphviz, resolvers e notificações;
- documentação existente do pacote;
- compatibilidade com formatos históricos que não são usados pela definição `equivalencia`.

## Próxima decisão necessária

Antes da implementação, deve ser definido se uma definição sincronizada por arquivo para uso do Equivalencia deve ser automaticamente marcada como `published` ou se o fluxo deve carregar explicitamente uma versão `draft`.

A recomendação para este consumidor é marcar a definição sincronizada como publicada: não há uma etapa administrativa de publicação no fluxo mínimo, e `loadDefinition()` sem versão deve carregar a definição executável.
