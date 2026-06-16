@extends('layouts.app')

@section('content')
<div class="mt-2">
    <h2 class="mb-4">Novo requerimento</h2>
    <ul class="list-group">
        @foreach($workflowDefinitions as $workflowDefinition)
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <a href="{{ route('workflows.createObject', ['definitionName' => $workflowDefinition->name]) }}" class="link-primary">
                    {{ $workflowDefinition->definition['title'] }}
                </a>

                {{-- <span>
                    {{ $workflowDefinition->description }}
                    <a href="{{ route('workflows.definition', $workflowDefinition->name) }}" class="btn btn-warning btn-sm ml-2">Listar</a>
                </span> --}}
            </li>
        @endforeach
    </ul>
</div>
@endsection