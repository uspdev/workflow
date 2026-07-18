@extends('uspdev-forms::layouts.app')

@section('header')
@endsection

@section('content')

<div class="col-2">@include('uspdev-workflow::definition.partials.tabs')</div>
<div class="card">
    <div class="card-header h4 card-header-sticky d-flex justify-content-between align-items-center">
      <div>
        <span class="text-danger">USPdev workflow</span> >
        Backups
      </div>
    </div>
    <div class="card-body">
      <table class="table table-bordered table-hover">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Descrição</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($workflowDefinitions as $workflowDefinition)
          @php
            $count = is_dir(config('uspdev-workflow.storagePath')) ? count(array_filter(scandir(config('uspdev-workflow.storagePath')), fn($filename) => str_contains($filename,$workflowDefinition->name))) : 0;
          @endphp
            <tr>
              <td>
                {{ $workflowDefinition->name }}
                <span class="badge badge-warning badge-pill" title="Backups existentes">
                  {{-- 
                    Exibe o número de backups da definição que existem atualmente
                  --}}
                  {{ $count }}
                </span>
              </td>
              <td>
                {{ $workflowDefinition->description }}
              </td>
              <td class="d-flex justify-content-start">
                @include('uspdev-workflow::definition.backups.partials.bckpgen-btn')
                @includeWhen($count > 0,'uspdev-workflow::definition.backups.partials.bckplist-btn')
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
    <div class="mt-2">
    @include('uspdev-workflow::definition.backups.partials.globalbckp-btn')
    </div>
@endsection
