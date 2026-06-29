# Documentação técnica, definições e modelo do Workflow

## Documentos

* [Dicionário de dados](dicionario_de_dados.md)
* [Classes e métodos](classes_e_metodos.md)
* [Especificação da coluna definition de workfowDefinition](definicao_workflow_definition.md)



## Contexto

Biblioteca do ecossistema USPDEV/Laravel

Estende a biblioteca symfony/workflow e adiciona outros contextos

Permite que um objeto do sitema seja associado a um workflow predefinido.

Possui interface para editar um workflow existente

Depende da biblioteca forms caso seja utilizado nas `transitions`.

## Casos de uso

Sistema deve possuir um objeto que vai ser tramitado no workflow. Exemplo de objeto ($equivalencia) classe Equivalencia.

Para iniciar o processo associando um objeto de Equivalencia ao Workflow use:

```php
$equivalencia = Equivalencia::load(1);
$wfObject = Workflow::start('equivalencia', $equivalencia);
```

Depois para ver o que consegue fazer:

    $state = $wfObject->workflowState();


Para aplicar uma transição, sendo $data os dados do formulário:

    $wfObject->apply('nome-transicao', $data);



# Regras de Negócio Cruciais (RNs)

Este documento é fundamental para definir as validações que o motor de workflow deve seguir.

---

# Esquema do JSON de Definição (Schema)

Como a lógica do fluxo (estados e transições) fica armazenada em uma coluna JSON chamada `definition`, um documento técnico detalhando a estrutura desse JSON é vital.

### O que incluir

* A hierarquia das chaves `places`, `transitions` e o `initial_place`.
* Isso é essencial para que o comando `workflow:sync` funcione corretamente ao importar arquivos de backup.

---

# Estrutura Sugerida da Documentação

## Dicionário e Modelagem

### Descrição

* O que você já tem.

---

## API e Interfaces

### Descrição

* Classes e Métodos.

---

## Casos de Uso

### Descrição

* Fluxos principais.

---

## Regras de Negócio e Permissões

### Descrição

* Validações e Acessos.

---

## Especificação da Definição (JSON)

### Descrição

* Estrutura da lógica do fluxo.

### DTOs - Data transfer object 

Todos DTOs devem possuir fromArray, toArray e validate.

FromArray deve implementar validação.

- WorkflowDefinitionData ✅
    - RoleDefinition
    - PlaceDefinition
    - TransitionDefinition ✅
        - métodos: 
            - resolveNotificationDestinations
        - DTO:
            - NotificationDefinition
            - BindingDefinition
    
### Enums

- WorkflowStatus

---

## Guia de Estilo e Testes

### Descrição

* Padrões de implementação.
