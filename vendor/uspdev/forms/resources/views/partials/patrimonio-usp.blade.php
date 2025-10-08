<div class="{{ $field['formGroupClass'] }}" id="uspdev-forms-patrimonio-usp">
  <label for="{{ $field['id'] }}" class="form-label">{{ $field['label'] }} {!! $field['requiredLabel'] !!}</label>
  <select id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="{{ $field['controlClass'] }}" @required($field['required'])>
    <option value="">Digite um número de patrimônio...</option>
    @if (isset($formSubmission) && isset($formSubmission->data[$field['name']]))
      @php
        $patrimonio = \Uspdev\Replicado\Bempatrimoniado::dump($formSubmission->data[$field['name']]);
      @endphp
      <option value="{{ $formSubmission->data[$field['name']] }}" selected>
        {{ $formSubmission->data[$field['name']] }}
        - {{ $patrimonio['epfmarpat'] }} - {{ $patrimonio['tippat'] }} - {{ $patrimonio['modpat'] }}
      </option>
    @elseif ($field['old'])
      @php
        $patrimonio = \Uspdev\Replicado\Bempatrimoniado::dump($field['old']);
      @endphp
      <option value="{{ $field['old'] }}" selected>
        {{ $field['old'] }}
        - {{ $patrimonio['epfmarpat'] }} - {{ $patrimonio['tippat'] }} -{{ $patrimonio['modpat'] }}
      </option>
    @endif
  </select>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {

    let attemptsPatr = 1;
    const maxAttemptsPatr = 50; // Tenta por 5 segundos (50 * 100ms)

    const intervalIdPatr = setInterval(() => {
      if (window.jQuery) {
        clearInterval(intervalIdPatr);
        console.log("Select2 carregou após " + attemptsPatr + " tentativas.");
        initSelect2Patr();
      } else if (attemptsPatr >= maxAttemptsPatr) {
        clearInterval(intervalIdPatr);
        console.error("jQuery não carregou após várias tentativas.");
      }
      attemptsPatr++;
    }, 100);

  });

  function initSelect2Patr() {
    var $oSelect2Patr = $('#{{ $field['id'] }}');

    $oSelect2Patr.select2({
      ajax: {
        url: '{{ route('form.find.patrimonio') }}',
        dataType: 'json',
        delay: 1000,
        processResults: function(data) {
          if (data.results.original.results) {
            return {
              results: data.results.original.results
            };
          }
          return data;
        }
      },
      allowClear: true,
      placeholder: 'Digite um número de patrimônio...',
      minimumInputLength: 9,
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
