<div class="{{ $field['formGroupClass'] }}">
  <div class="form-label">
    {{ $field['label'] }} {!! $field['requiredLabel'] !!}
  </div>

  <div class="form-check form-check-inline checkboxForm" 
       data-checkbox-group-value="{{ $field['name'] }}[]" 
       @if(!empty($field['requiredLabel'])) data-required="true" @endif>
    @foreach ($field['options'] as $option)
      <input 
        id="{{ $field['id'] }}-{{ $loop->iteration }}"
        type="checkbox" 
        name="{{ $field['name'] }}[]" 
        value="{{ $option['value'] }}"
        class="form-check-input" 
        @checked(in_array($option['value'], (array) $field['old']))
      >
      <label class="form-check-label mr-3" for="{{ $field['id'] }}-{{ $loop->iteration }}">
        {{ $option['label'] }}
      </label>
    @endforeach
  </div>
</div>

<script>
document.getElementById('generatedForm').addEventListener('submit', function(e) {
    var requiredGroups = document.querySelectorAll('.checkboxForm[data-required="true"]');
    
    for (var i = 0; i < requiredGroups.length; i++) {
        var group = requiredGroups[i];
        var groupName = group.getAttribute('data-checkbox-group-value');
        if (document.querySelectorAll('input[name="'+groupName+'"]:checked').length === 0) {
            alert('Por favor, selecione ao menos uma opção para o campo obrigatório.');
            e.preventDefault();
            break;
        }
    }
});
</script>
