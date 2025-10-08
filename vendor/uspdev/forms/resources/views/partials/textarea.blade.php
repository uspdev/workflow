<div class="{{ $field['formGroupClass'] }}">

  <label for="{{ $field['id'] }}">{{ $field['label'] }} {!! $field['requiredLabel'] !!}</label>

  <textarea id="{{ $field['id'] }}" 
    name="{{ $field['name'] }}" 
    class="{{ $field['controlClass'] }}" 
    @required($field['required'])
    >{{  $field['value'] ?? $field['old'] }}</textarea>

</div>