@extends('uspdev-workflow::layouts.app')

@section('content')
  <div class="mt-5">
    <a href="{{ route('workflows.list-definitions') }}" class="link-primary"><i class="fas fa-arrow-left"></i> Voltar aos
      Workflows</a>
    <h1 class="mb-4">Editar workflow {{ $workflow->name }}</h1>

    <form action="{{ route('workflows.updateDefinition') }}" method="POST">
      @csrf

      <div class="form-group mb-3">
        <label for="name">Nome da workflow definition</label>
        <input type="text" name="name" class="form-control" value="{{ $workflow->name }}" readonly>
      </div>

      <div class="form-group mb-3">
        <label for="description">Descrição da definition</label>
        <input type="text" name="description" class="form-control" value="{{ $workflow->description }}">
      </div>

      <div class="form-group mb-3">
        <label for="definition">Definição (JSON)</label>
        <textarea name="definition" class="form-control" rows=15>{{ json_encode($workflow->definition, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</textarea>
      </div>

      <button type="submit" class="btn btn-success">Salvar</button>
    </form>
  </div>
@endsection
