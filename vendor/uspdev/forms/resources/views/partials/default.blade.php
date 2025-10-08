<div class="{{ $field['formGroupClass'] }}">
  
  <label for="{{ $field['id'] }}">{{ $field['label'] }} {!! $field['requiredLabel'] !!}</label>

  <input id="{{ $field['id'] }}" type="{{ $field['type'] }}" name="{{ $field['name'] }}" class="{{ $field['controlClass'] }}"
    value="{{  $field['value'] ?? $field['old'] }}" @required($field['required'])>
    
</div>
