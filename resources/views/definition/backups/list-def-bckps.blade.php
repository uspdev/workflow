@extends('uspdev-forms::layouts.app')

@section('content')

<div class="card">
    <div class="card-header h4 card-header-sticky d-flex justify-content-between align-items-center">
      <div>
        <span class="text-danger">USPdev workflow</span> >
        Backups > {{ $workflowDefinition->name }} >
        <a href="{{ route('workflows.backups-idx') }}" class="btn btn-sm btn-outline-secondary ml-2">Voltar</a>
      </div>
    </div>
    <div class="card-body">
      <div>@include('uspdev-workflow::definition.backups.partials.bckpgen-btn')</div>
      <br>
      <table class="table table-bordered table-hover">
        <thead>
          <tr>
            <th>Data de criação</th>
            <th>Última modificação</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($time_data as $created_time => $updt_time)
            <tr>
              <td>
                {{ str_replace('_',' - ',str_replace('-','/',$created_time)) }}
              </td>
              <td>
                {{ str_replace('_',' - ',str_replace('-','/',$updt_time)) }}
              </td>
              <td class="d-flex justify-content-start align-item-centered">
                @include('uspdev-workflow::definition.backups.partials.restore-btn')
                @include('uspdev-workflow::definition.backups.partials.bckpremove-btn')
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
  <div class="mt-2">
    @includeWhen(count($time_data) > 0,'uspdev-workflow::definition.backups.partials.defbckpremoveall-btn')
  </div>
@endsection
