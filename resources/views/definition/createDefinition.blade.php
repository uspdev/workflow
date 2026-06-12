@extends('layouts.app')

@section('content')
  <div class="mt-5">
    <a href="{{ route('workflows.list-definitions') }}" class="link-primary"><i class="fas fa-arrow-left"></i> Voltar aos Workflows</a>
    <h1 class="mb-4">Criar nova workflow definition</h1>

    <form action="{{ route('workflows.store-definition') }}" method="POST">
      @csrf

      <div class="form-group mb-3">
        <label for="name">Nome da workflow definition</label>
        <input type="text" name="name" class="form-control" value="{{ old('name') }}" required>
      </div>

      <div class="form-group mb-3">
        <label for="description">Descrição da definition</label>
        <input type="text" name="description" class="form-control" value="{{ old('description') }}" required>
      </div>

      <div class="form-group mb-3">
        <label for="definition">Definição (JSON)</label>
        <textarea name="definition" class="form-control" required>{{ old('definition') }}</textarea>
      </div>

      <button type="submit" class="btn btn-success">Criar</button>
    </form>
  </div>
@endsection
