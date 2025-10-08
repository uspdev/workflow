{{-- Gerador de campo para disciplina-usp --}}

<div class="{{ $field['formGroupClass'] }}" id="uspdev-forms-disciplina-usp">

  <label for="{{ $field['id'] }}" class="form-label">{{ $field['label'] }} {!! $field['requiredLabel'] !!}</label>
  <select id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="{{ $field['controlClass'] }}" @required($field['required'])>
    <option value="">Selecione uma disciplina...</option>
    @if (isset($formSubmission) && isset($formSubmission->data[$field['name']]))
      <option value="{{ $formSubmission->data[$field['name']] }}" selected>{{ $formSubmission->data[$field['name']] }}
        {{ \Uspdev\Replicado\Graduacao::nomeDisciplina($formSubmission->data[$field['name']]) }}</option>
    @elseif ($field['old'])
      <option value="{{ $field['old'] }}" selected>
        {{ $field['old'] }} {{ \Uspdev\Replicado\Graduacao::nomeDisciplina($field['old']) }}
      </option>
    @endif
  </select>

</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {

    let attemptsDisc = 1;
    const maxAttemptsDisc = 50; // Tenta por 5 segundos (50 * 100ms)

    const intervalIdDisc = setInterval(() => {
      if (window.jQuery) {
        clearInterval(intervalIdDisc);
        console.log("Select2 carregou após " + attemptsDisc + " tentativas.");
        initSelect2Disc();
      } else if (attemptsDisc >= maxAttemptsDisc) {
        clearInterval(intervalIdDisc);
        console.error("jQuery não carregou após várias tentativas.");
      }
      attemptsDisc++;
    }, 100);

  });

  function initSelect2Disc() {
    var $oSelect2Disc = $('#{{ $field['id'] }}');

    $oSelect2Disc.select2({
      ajax: {
        url: '{{ route('form.find.disciplina') }}',
        dataType: 'json',
        delay: 1000
      },
      allowClear: true,
      placeholder: 'Selecione uma disciplina...',
      minimumInputLength: 3,
      theme: 'bootstrap4',
      width: 'resolve',
      language: 'pt-BR'
    });

    // Coloca o foco no campo de busca ao abrir o Select2
    $(document).on('select2:open', () => {
      document.querySelector('.select2-search__field').focus();
    });
  }
</script>
