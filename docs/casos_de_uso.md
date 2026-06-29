## Casos de uso

O sistema deve permitir que objetos de domínio sejam controlados por um workflow, representando seu ciclo de vida através de estados e transições bem definidas. Esses objetos são entidades do sistema, como por exemplo uma classe Equivalencia, que representa um processo de equivalência acadêmica.

### Iniciando um workflow

Para iniciar o fluxo e associar um objeto ao workflow definido, utiliza-se o método start, informando o nome do workflow e a instância do objeto:

```php
$equivalencia = Equivalencia::load(1);
$workflowObject = Workflow::start('equivalencia', $equivalencia);
```

A partir desse momento, o objeto passa a ser gerenciado pelo workflow correspondente.

### Consultando o estado atual

Após a associação do objeto ao workflow, é possível consultar seu estado atual dentro do fluxo e também as transições disponíveis a partir desse ponto.

```php
$place = $workflowObject->current_place;
$transitions = $workflowObject->transitions();
```

O atributo current_place representa o estado atual (place) em que o objeto se encontra dentro do workflow, enquanto transitions() retorna as transições possíveis a partir desse estado.

### Executando transições

As mudanças de estado são realizadas através de transições nomeadas. Caso a transição exija dados adicionais (por exemplo, provenientes de um formulário), estes podem ser passados no momento da execução:

```php
$workflowObject->apply('nome-transicao', $data);
```

O parâmetro $data representa os dados necessários para a execução da transição, normalmente originados de formulários dinâmicos associados ao workflow.

### Histórico do workflow

Cada instância de workflow mantém um histórico das transições executadas, permitindo auditoria e rastreabilidade completa do ciclo de vida do objeto.

O histórico pode ser acessado a partir da instância do workflow:

$history = $workflowInstance->history();

Cada item do histórico contém informações como transição executada, estado de origem e destino, usuário responsável (quando aplicável), data/hora e contexto adicional fornecido na execução.

Exemplo de uso:

```php
foreach ($workflowInstance->history() as $event) {
    echo $event->transition;
    echo $event->from;
    echo $event->to;
    echo $event->created_at;
}
```
