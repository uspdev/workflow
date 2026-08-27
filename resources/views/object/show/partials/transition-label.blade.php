{{-- Exibe o label amigavel da transicao do formulario atual --}}
{{ $formulario['transition']->label ?? Str::replace('_', ' ', ucfirst($formulario['transition']->name)) }}
