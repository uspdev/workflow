{{-- Exibe o label amigavel da transicao do formulario atual --}}
{{ $workflowObjectData['workflowDefinition']->definition['transitions'][$formulario['transition']]['label'] ??
    Str::replace('_', ' ', ucfirst($formulario['transition'])) }}
