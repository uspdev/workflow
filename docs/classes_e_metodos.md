# Classes e métodos

----------------------------------------------------
## class Workflow

* Métodos de busca retornam null quando não houver correspondência.

### Métodos

#### Workflow::start(string $workflowName, Model $model): WorkflowObject ✅

* Cria uma nova instância do workflow para o modelo informado, posicionando-a nos estados definidos em `initial_places`.

#### Workflow::find(Model $model): ?WorkflowObject ✅

* Retorna a instância de workflow associada ao modelo informado.

#### Workflow::loadDefinition(string $name, ?int $version = null): WorkflowDefinition ✅

* Retorna a definição de um workflow.
* Se `$version` não for informado, retorna a versão publicada do workflow.

----------------------------------------------------
## class WorkflowDefinition extends Model

* Métodos de busca retornam null quando não houver correspondência.

### Métodos

#### $workflow->place(string $name): array 📝

* retorna dados do place com o nome fornecido.

#### $workflow->transition(string $name): array 📝

* retorna dados de uma transition


----------------------------------------------------
## class WorkflowObject extends Model

* Métodos de busca retornam null quando não houver correspondência.

### Métodos

#### $object->apply(string $transition, array $context = [], ?User $user = null): bool ✅ ⚠️

* Aplica uma `transition` em `$object`.
* possui descrição complementar

### $object->transitions(): array 📝

* Retorna as transições associadas ao place atual.

#### $object->workflowState(): array 📝

* retorna o que UI precisa:
  - actors
  - transitions
* possui descrição complementar

#### $object->can(string $transition, ?User $user = null): bool 📝

* Verifica se `transition` pode de ser executada.

#### $object->model(): Model 📝

* Retorna a instância do Model vinculada a este WorkflowObject.

#### $object->history(): Collection WorkflowHistory 📝

* Retorna o histórico de transitions registrado em WorkflowHistory

---

## Descrição complementar de $object->apply()

Responsável por aplicar uma transição no workflow, validando regras e atualizando o estado da instância. Inclui persistência e registro de histórico.

### Etapas

* valida a transição
* valida permissões
* executa a transição
* atualiza current_places
* registra workflow_history
* persiste alterações

### $context

* informações adicionais a serem tratadas como ???????
* deve processar dados do form associado
* vale criar uma classe transitionContext para forçar um formato???

---

## Descrição complementar de $object->workflowState()

### Exemplo de retorno do método

```php
[
    'current_places' => ['analise'],

    'actors' => [
        'Maria'
    ],

    'transitions' => [ // transitions possíveis
        [
            'name' => 'aprovar',
            'label' => 'Aprovar',
            'form' => 'nome do form'
        ],
        [
            'name' => 'rejeitar',
            'label' => 'Rejeitar',
            'form' => ''
        ]
    ]
]
```
