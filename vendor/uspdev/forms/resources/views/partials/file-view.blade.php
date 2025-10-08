@php
  $path = $submission['data'][$field['name']]['stored_path'] ?? null;
  $filename = $submission['data'][$field['name']]['original_name'] ?? null;
@endphp

@if (isset($submission['data'][$field['name']]))
  <span title="{{ $filename }}">
    <a href="{{ route('form-submissions.download-file', ['formDefinition' => $submission->form_definition_id, 'formSubmission' => $submission->id, 'fieldName' => $field['name']]) }}"
      target="_blank">
      {{ Illuminate\Support\Str::limit($filename, 30) }}
    </a>
  </span>
@else
  n/a
@endif
