# Classes e métodos

## class WorkflowDefinition

### Métodos

#### Workflow::load(string $name, ?int version = null): WorkflowDefinition

* Se não informado, retorna a versão 'publicado'

#### $workflow->places(): array

* retorna todos os places do workflow

#### $workflow->transitions(): array

* retorna todas as transitions do workflow

#### $workflow->place(string $name)

* retorna dados de um place

#### $workflow->transition(string $name)

* retorna dados de uma transition

#### $workflow->createObject(Model $model): WorkflowObject

* Vincular um objeto (ex. Equivalencia) a um workflow específico, iniciando seu ciclo de vida em um estado predefinido.

---

## class WorkflowObject

### Métodos

#### $object->currentPlaces(): array

* Retorna o place atual do objeto.

#### $object->can(string $transition, ?User $user = null): bool

* Verifica se `transition` pode de ser executada.

#### $object->apply(string $transition, array $context = [], ?User $user = null): bool

* Executa uma transition em $object
* possui descrição complementar

#### $object->enabledTransitions(): Collection

* lista transições possíveis

#### $object->workflowState()

* retorna tudo que UI precisa, place, actors, actions, forms
* possui descrição complementar

#### $object->history(): Collection WorkflowHistory

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
* vale criar uma classe transitionContext para forçar um formato???

---

## Descrição complementar de $object->workflowState()

### Exemplo de retorno do método

```php
[
    'current_place' => 'analise',

    'actors' => [
        'Maria'
    ],

    'actions' => [ // transitions possíveis
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
