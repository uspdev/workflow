@extends('uspdev-workflow::layouts.app')

@section('header')
@endsection 

@section('content')

<div class="col-2">@include('uspdev-workflow::definition.partials.tabs')</div>
<div class="card">
    <div class="card-header h4 card-header-sticky d-flex justify-content-between align-items-center">
      <div>
        <span class="text-danger">USPdev workflow</span> >
        Definições
        <a href="{{ route('workflows.create-definition') }}" class="btn btn-sm btn-primary">Nova Definição</a>
      </div>
    </div>
    <div class="card-body">
      <table class="table table-bordered table-hover text-center">
        <thead>
          <tr>
            <th>Nome</th>
            <th>Versão</th>
            <th>Descrição</th>
            <th>Status</th>
            <th>Ações</th>
          </tr>
        </thead>
        <tbody>
          @foreach ($workflowDefinitions as $workflowDefinition)
            <tr>
              <td>
                <a href="{{ route('workflows.showDefinition',['definitionName' => $workflowDefinition->name, 'version' => $workflowDefinition->version]) }}">{{ $workflowDefinition->name }}</a>
                
              </td>
              <td>
                {{ $workflowDefinition->version }}
              </td>
              <td>
                {{ $workflowDefinition->description }}
              </td>
              <td>
                @switch($workflowDefinition->status->value)
                  @case('published')
                    <span class="badge-success">Publicado</span>
                    @break
                  @case('draft')
                    <span class="badge-warning">Draft</span>
                  @break
                  @default
                    <span class="badge-danger">Arquivado</span>
                @endswitch
              </td>
              <td class="d-flex justify-content-start">
                @include('uspdev-workflow::definition.partials.edit-btn')
                @include('uspdev-workflow::definition.partials.remove-btn')
                @includeWhen($workflowDefinition->status->value != 'published', 'uspdev-workflow::definition.partials.publish-btn')
                @includeWhen($workflowDefinition->status->value === 'published', 'uspdev-workflow::definition.partials.draft-btn')
                <a href="{{ route("workflows.createObject", ['definitionName' => $workflowDefinition->name, 'version' => $workflowDefinition->version]) }}" class="btn btn-sm btn-success">Criar Objeto</a>
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
    </div>
  </div>
@endsection
