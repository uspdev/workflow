@extends('uspdev-workflow::layouts.app')

@section('content')
  <a href="{{ route('workflows.list-definitions') }}" class="link-primary"><i class="fas fa-arrow-left"></i> Voltar aos
    Workflows</a>
  <div class="row">
    @foreach ($workflowDefinitionData['roles'] as $role)
      <div class="col-md-4">

        <div class="card m-3">
          <div class="card">
            <form method="post" id="form-{{ $role['name'] }}" action="{{ route('workflows.setuser') }}">
              @csrf
              @method('put')
              <input type="hidden" name="role" value="{{ $role['name'] }}">
              <input type="hidden" name="workflowDefinitionName" value="{{ $workflowDefinitionData['definitionName'] }}">
              <div class="card-header py-1">
                <span class="h5">
                  {{ $role['label'] }} - 
                  {{ $role['source'] ?? '' }}
                  @include('uspdev-workflow::definition.partials.codpes-adicionar-btn', ['wf_id' => $workflowDefinitionData['workflowDefinition']->id])
                </span><br>
              </div>
              <div class="card-body py-1">
                @foreach (Uspdev\Workflow\Models\WorkflowDefinition::findUsersWithRole($role['name'], $workflowDefinitionData['definitionName'], $workflowDefinitionData['version']) as $user)
                  <div class="hover">
                    <span>{{ $user->name }}</span>
                    <span class="hide">
                      @if ($user->codpes != auth()->user()->codpes)
                        @include('uspdev-workflow::definition.partials.codpes-remover-btn', ['codpes' => $user->codpes, 'wf_id' => $workflowDefinitionData['workflowDefinition']->id])
                      @endif
                    </span>
                  </div>
                @endforeach
              </div>
            </form>

          </div>
        </div>
      </div>
    @endforeach
  </div>
  </div>
  <div class="d-flex align-items-center ">
    <h1 class="mb-4 ml-2">Detalhes do workflow {{ $workflowDefinitionData['definitionName'] }}</h1>
    <a href="{{ route('workflows.editDefinition', ['definitionName' =>$workflowDefinitionData['definitionName'], 'version' => $workflowDefinitionData['version']]) }}"
      class="btn btn-warning btn-sm mb-3 ml-4">Editar workflow</a>
  </div>
  <div class="row ml-2">
    <div class="col-md-7">
      <h2>Definições</h2>
      <pre>{{ $workflowDefinitionData['formattedJson'] }}</pre>
    </div>
    <div class="col-md-5">
      <img src="{{ asset('../' . $workflowDefinitionData['path']) }}" class="img-fluid w-300">
    </div>
  @endsection
