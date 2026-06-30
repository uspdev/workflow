## Casos de uso

O sistema deve permitir que objetos de domínio sejam controlados por um workflow, representando seu ciclo de vida através de estados e transições bem definidas. Esses objetos são entidades do sistema, como por exemplo uma classe Equivalencia, que representa um processo de equivalência acadêmica.

### Iniciando um workflow

Para iniciar o fluxo e associar um objeto ao workflow definido, utiliza-se o método start, informando o nome do workflow e a instância do objeto:

```php
$equivalencia = Equivalencia::load(1);
$workflowObject = Workflow::start('equivalencia', $equivalencia);
```

A partir desse momento, o objeto passa a ser gerenciado pelo workflow correspondente.

### Carregando um workflow existente

Para carregar um workflow associado a um objeto da sua aplicação.

```php
$equivalencia = Equivalencia::load(1);
$workflowObject = Workflow::find($equivalencia);
```


### Consultando o estado atual

Após a associação do objeto ao workflow, é possível consultar seu estado atual dentro do fluxo e também as transições disponíveis a partir desse ponto.

```php
$place = $workflowObject->current_place;
$transitions = $workflowObject->transitions();
```

O atributo current_place representa o estado atual (place) em que o objeto se encontra dentro do workflow, enquanto transitions() retorna as transições possíveis a partir desse estado.

### Executando transições

As mudanças de estado são realizadas por meio de transições nomeadas. Quando a transição exige informações adicionais, como dados de um formulário, esses dados devem ser fornecidos no momento da execução.

```php
try {
    $workflowObject->apply($transitionName, $request);
    return redirect()->route(...);
} catch (ValidationException $e) {
    return back()->withErrors($e->errors())->withInput();
}
```

O parâmetro $request contém os dados necessários para a execução da transição. Quando a transição possui um formulário associado, a interface deve apresentá-lo ao usuário antes da execução.

Em uma view Blade, o formulário pode ser renderizado da seguinte forma:

```php
{{ $transition->form()->generateHtml() }}
```

Após o envio, o formulário é validado e seus dados são persistidos. Se a validação falhar, uma `ValidationException` é lançada, permitindo que a aplicação devolva o usuário ao formulário com as mensagens de erro e os valores previamente informados (`old()`). Somente após uma validação bem-sucedida a transição é executada e o workflow tem seu estado atualizado.


### Histórico do workflow

Cada instância de workflow mantém um histórico das transições executadas, permitindo auditoria e rastreabilidade completa do ciclo de vida do objeto.

O histórico pode ser acessado a partir da instância do workflow:

```php
$history = $workflowInstance->history();
```

Cada registro do histórico contém informações como a transição executada, o estado de origem, o estado de destino, o usuário responsável pela ação (quando aplicável), a data e hora da execução, a submissão do formulário associado (quando existir) e metadados adicionais.

Exemplo de uso:

```php
foreach ($workflowObject->history()->with(['user', 'formSubmission'])->get() as $entry) {
    echo $entry->transition;
    echo $entry->from_place;
    echo $entry->to_place;
    echo $entry->created_at;
}
```


