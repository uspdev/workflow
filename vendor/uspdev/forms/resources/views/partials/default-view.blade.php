@php
  $display = $submission['data'][$field['name']] ?? 'n/a';
  if (is_array($display))
      $display = implode(', ', $display);
@endphp

<span title="{{ $display }}">
  {{ Illuminate\Support\Str::limit($display, 100) }}
</span>
