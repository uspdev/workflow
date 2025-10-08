@php
  $codpes = $submission['data'][$field['name']] ?? 'n/a';
  $display = $codpes . ' - ' . (\Uspdev\Replicado\Pessoa::retornarNome($codpes) ?? 'n/a');
@endphp

<span title="{{ $display }}">
  {{ Illuminate\Support\Str::limit($display, 100) }}
</span>
