<div class="{{ $field['formGroupClass'] }}">

  <label for="{{ $field['id'] }}">{{ $field['label'] }} {!! $field['requiredLabel'] !!}</label>

  <select id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="{{ $field['controlClass'] }}" @required($field['required'])>

    <option selected disabled hidden value="">Selecione um ..</option>
    @foreach ($field['options'] as $o)
      <option value="{{ $o }}" @selected($field['old'] == $o)>
        {{ $o }}
      </option>
    @endforeach
  </select>
</div>
