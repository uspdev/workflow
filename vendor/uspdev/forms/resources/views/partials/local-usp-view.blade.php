@php
  $codlocusp = $submission['data'][$field['name']] ?? 'n/a';
  $local = \Uspdev\Replicado\Estrutura::procurarLocal($codlocusp)[0] ?? 'n/a';
  if (!empty($local) && is_array($local)) {
      $title =$codlocusp
        . ' - ' .($local['epflgr'] ?? 'n/a')
        . ', ' .($local['numlgr'] ?? 'n/a')
        . ' (' .($local['sglund'] ?? 'n/a')
        . ') - Bloco: ' .($local['idfblc'] ?? 'n/a')
        . ' - Andar: ' .($local['idfadr'] ?? 'n/a')
        . ' - ' .($local['idfloc'] ?? 'n/a');
  } else {
      $title = $codlocusp;
  }
@endphp

<span title="{{ $title }}">
  {{ $codlocusp }}
</span>
