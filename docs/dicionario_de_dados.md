Segue apenas com padronização de formatação em Markdown, sem alterar o conteúdo.

# Tabelas

## workflow_definitions

Contém as definições de workflows do sistema.

### Campos

| Campo        | Tipo/Observação       |
| ------------ | --------------------- |
| id           |                       |
| name         | UNIQUE(name, version) |
| description  |                       |
| definition   |                       |
| version      |                       |
| status       |                       |
| published_at |                       |
| created_at   |                       |
| updated_at   |                       |

### Descrição dos campos

**name**

* identifica o workflow definition
* Deve ser único em conjunto com version

**description**

* texto descritivo simples

**definition**

* JSON contendo a definição completa do workflow
* [Especificação da coluna definition de workfowDefinition](definicao_workflow_definition.md)


**version**

* incrementado a cada alteração de comportamento do workflow

**status**

* Enum:

  * Rascunho
  * Publicado
  * Desativado
* Para cada name pode existir apenas uma versão com status = publicado

**published_at**

* Data e hora da publicação da versão

### Restrições

* UNIQUE(name, version)
* Para cada name pode existir apenas uma versão com status = publicado
* Versões publicadas são imutáveis

  * Exceção: alterações exclusivamente em campos de apresentação (labels, descrições e textos exibidos ao usuário) podem ser realizadas sem criação de nova versão, desde que não modifiquem o comportamento do workflow
* Workflow_objects sempre referenciam uma versão específica da definição

---

## workflow_objects

Contém as instâncias de workflow.

### Campos

| Campo                  | Tipo/Observação                             |
| ---------------------- | ------------------------------------------- |
| id                     | PK                                          |
| workflow_definition_id | FK                                          |
| object_type, object_id | MORPH                                       |
| current_places         | JSON                                        |
| variables              | JSON                                        |
| created_at             |                                             |
| updated_at             |                                             |

### Descrição dos campos

**object_type e object_id**

* identifica o objeto de negócio associado à instância do workflow.
* Relacionamento polimórfico MorphOne.

**current_places**

* JSON contendo os places atualmente ativos
* O symfony/workflow permite que uma `transition` aponte para mais de um `place`

**variables** (opcional)

* Permite parametrizar o workflow sem alterar sua definição
* Os valores podem ser referenciados pela definição do workflow
* Ex.:

  ```json
  {
    "parecerista": "123456",
    "relator": "654321"
  }
  ```
* e referenciados na definição por:

  ```json
  "roles": ["@parecerista"]
  ```

### Restrições

* workflow_definition_id deve referenciar uma versão específica de workflow_definitions
* object_type e object_id devem identificar unicamente o objeto associado ao workflow
* Uma instância de workflow deve estar associada a exatamente um objeto de negócio
* current_places deve conter apenas places definidos na workflow_definition referenciada
* Os dados armazenados em data não devem alterar a estrutura do workflow, apenas parametrizar sua execução

---

## workflow_history

Histórico de transições.

### Campos

| Campo              | Tipo/Observação |
| ------------------ | --------------- |
| id                 |                 |
| workflow_object_id | FK              |
| transition         |                 |
| from_places        |                 |
| to_places          |                 |
| user_id            | FK              |
| form_submission_id | FK, nullable    |
| metadata           | JSON, nullable  |
| created_at         |                 |
| updated_at         |                 |

### Descrição dos campos

**transition**

* Nome da transição executada
* Ex.: aprovar, reprovar, encaminhar

**from_places**

* JSON contendo os places ativos antes da execução da transição

**to_places**

* JSON contendo os places ativos após a execução da transição

**metadata**

* outras informações de histórico/auditoria a serem utilizados no futuro

### Restrições

* workflow_object_id deve referenciar workflow_objects
* user_id deve identificar o responsável pela transição executada
* from_places e to_places devem conter apenas places definidos na workflow_definition associada ao workflow_object
* Os registros são imutáveis após sua criação
* Cada registro representa uma única execução de transição



## workflow_role_users

Quais usuários podem atuar em cada role do workflow.

### Campos

| Campo         | Tipo/Observação |
| ------------- | --------------- |
| id            |                 |
| workflow_name |                 |
| role_name     |                 |
| user_id       | FK              |
| created_at    |                 |
| updated_at    |                 |

### Restrições

* UNIQUE(workflow_name, role_name, user_id)
* user_id deve referenciar a tabela `users`.

### Observações

* Caso uma role seja removida da definição do workflow, os registros associados poderão ficar órfãos. Necessita de tratamento.

---

## workflow_role_emails

Permite associar endereços de e-mail a uma role para fins de notificação.

### Campos

| Campo         | Tipo/Observação |
| ------------- | --------------- |
| id            |                 |
| workflow_name |                 |
| role_name     |                 |
| email         |                 |
| created_at    |                 |
| updated_at    |                 |

### Restrições

* UNIQUE(workflow_name, role_name, email)

### Observações

* Caso uma role seja removida da definição do workflow, os registros associados poderão ficar órfãos. Necessita de tratamento.







