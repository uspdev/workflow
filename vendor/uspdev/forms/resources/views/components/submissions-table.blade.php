{{-- 
  Mostra uma tabela com as submissões de um formulário.
  
  Chamar com:
  <x-uspdev-forms::submissions-table :form="$form"></x-submission-table>

  masakik, 29/5/2025
 --}}
@props(['form' => $form])

@php
  $definition = $form->getDefinition();
@endphp
<div class="table-responsive">
  <table class="table table-striped table-bordered datatable-simples">
    <thead>
      <tr>
        @foreach ($definition->flattenFields() as $field)
          <th>{{ $field['label'] ?? $field['name'] }}</th>
        @endforeach
        <th style="width: 40px"></th>
      </tr>
    </thead>
    <tbody>
      @foreach ($form->listSubmission($form->name) as $submission)
        <tr>
          @foreach ($form->getDefinition()->flattenFields() as $field)
            <td>
              @includeFirst([
                  'uspdev-forms::partials.' . $field['type'] . '-view',
                  'uspdev-forms::partials.default-view',
              ])
            </td>
          @endforeach
          <td>
            <div class="d-flex">
              @include('uspdev-forms::submission.partials.edit-btn')
              <div class="px-1"></div>
              @include('uspdev-forms::submission.partials.delete-btn')
            </div>
          </td>
        </tr>
      @endforeach
    </tbody>
  </table>
</div>
