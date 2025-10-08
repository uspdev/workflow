@php
  $numpat = $submission['data'][$field['name']] ?? 'n/a';
  $title = '';
  if ($bemPatrimoniado = \Uspdev\Replicado\Bempatrimoniado::dump($numpat)) {
      $title = $bemPatrimoniado['epfmarpat'] . ' - ' . $bemPatrimoniado['tippat'] . ' - ' . $bemPatrimoniado['modpat'];
  }
@endphp

<span title="{{ $title }}">
  {{ str_pad(substr($numpat, 0, -6), 3, '0', STR_PAD_LEFT) . '.' . str_pad(substr($numpat, -6), 6, '0', STR_PAD_LEFT) }}
</span>
