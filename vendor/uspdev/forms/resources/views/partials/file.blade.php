<div class="{{ $field['formGroupClass'] }} w-50">

  <label>
    {{ $field['label'] }} {!! $field['requiredLabel'] !!}
    <span class="badge badge-info">{{ $field['accept'] ?? 'Todos' }}</span>
  </label>
  <div class="custom-file">
    <input type="file" id="{{ $field['id'] }}" name="file[{{ $field['name'] }}]"
      class="custom-file-input {{ $field['controlClass'] }}"
      @if (!empty($field['accept'])) accept="{{ $field['accept'] }}" @endif @required(!empty($field['required']) && $field['required'])>

    <label class="custom-file-label" for="{{ $field['id'] }}">
      {{ $field['old']['original_name'] ?? 'Escolher arquivo...' }}
    </label>

  </div>

  @if (isset($field['old']['original_name']))
    <div class="form-check">
      <input class="form-check-input" type="checkbox" value="{{ $field['name'] }}" id="remover_{{ $field['id'] }}"
        name="remover[]">
      <label class="form-check-label text-danger" for="remover_arquivo">
        Remover arquivo atual
      </label>
    </div>
  @endif

</div>

<script src="https://cdn.jsdelivr.net/npm/bs-custom-file-input/dist/bs-custom-file-input.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    bsCustomFileInput.init();
  });
</script>
