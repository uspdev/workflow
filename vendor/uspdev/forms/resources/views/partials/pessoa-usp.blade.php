{{--  
  a rota senhaunicaFindUsers previsa ser ajustado com a permissão correta no config/senhaunica.php
  Masakik, em 20/3/2025 
--}}

<div class="{{ $field['formGroupClass'] }}" id="uspdev-forms-pessoa-usp">

  <label for="{{ $field['id'] }}" class="form-label">{{ $field['label'] }} {!! $field['requiredLabel'] !!}</label>

  <select id="{{ $field['id'] }}" name="{{ $field['name'] }}" class="{{ $field['controlClass'] }}" @required($field['required'])>
    <option value="">Digite o nome ou codpes..</option>
    @if (isset($formSubmission) && isset($formSubmission->data[$field['name']]))
      <option value="{{ $formSubmission->data[$field['name']] }}" selected>
        {{ $formSubmission->data[$field['name']] }}
        {{ \Uspdev\Replicado\Pessoa::retornarNome($formSubmission->data[$field['name']]) }}
      </option>
    @elseif ($field['old'])
      <option value="{{ $field['old'] }}" selected>
        {{ $field['old'] }} {{ \Uspdev\Replicado\Pessoa::retornarNome($field['old']) }}
      </option>
    @elseif(\Illuminate\Support\Facades\Auth::user())
      <option value="{{ \Illuminate\Support\Facades\Auth::user()->codpes }}" selected>
        {{ \Illuminate\Support\Facades\Auth::user()->codpes }}
        {{ \Uspdev\Replicado\Pessoa::retornarNome(\Illuminate\Support\Facades\Auth::user()->codpes) }}
      </option>
    @endif
  </select>
</div>

<script>
  document.addEventListener('DOMContentLoaded', function() {

    let attemptsPessoa = 1;
    const maxAttemptsPessoa = 50; // Tenta por 5 segundos (50 * 100ms)

    const intervalIdPessoa = setInterval(() => {
      if (window.jQuery) {
        clearInterval(intervalIdPessoa);
        console.log("Select carregou após " + attemptsPessoa + " tentativas.");
        initSelect2Pessoa();
      } else if (attemptsPessoa >= maxAttemptsPessoa) {
        clearInterval(intervalIdPessoa);
        console.error("jQuery não carregou após várias tentativas.");
      }
      attemptsPessoa++;
    }, 100);

  });

  function initSelect2Pessoa() {
    var $oSelect2Pessoa = $('#{{ $field['id'] }}');

    $oSelect2Pessoa.select2({
      ajax: {
        url: '{{ route('form.find.pessoa') }}',
        dataType: 'json',
        delay: 1000
      },
      allowClear: true,
      placeholder: 'Digite o nome ou codpes..',
      minimumInputLength: 4,
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
