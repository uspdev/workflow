@php
  $coddis = $submission['data'][$field['name']] ?? 'n/a';
  $title = $coddis . ' - ' . (\Uspdev\Replicado\Graduacao::nomeDisciplina($coddis) ?? 'n/a');
@endphp

<span title="{{ $title }}">
  {{ $coddis }}
</span>
