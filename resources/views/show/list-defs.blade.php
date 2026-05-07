@extends('uspdev-forms::layouts.app')

@section('header')
@endsection

@section('content')

<div class="col-2">@include('uspdev-workflow::show.partials.tabs')</div>
<div class="card">
    <div class="card-header h4 card-header-sticky d-flex justify-content-between align-items-center">
      <div>
        <span class="text-danger">USPdev workflow</span> >
        Definições
        <a href="{{ route('workflows.create-definition') }}" class="btn btn-sm btn-primary">Nova Definição</a>
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
            <tr>
              <td>
                <a href="{{ route('workflows.showDefinition',$workflowDefinition->name) }}">{{ $workflowDefinition->name }}</a>
              </td>
              <td>
                {{ $workflowDefinition->description }}
              </td>
              <td class="d-flex justify-content-start">
                @include('uspdev-workflow::show.partials.edit-btn')
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
