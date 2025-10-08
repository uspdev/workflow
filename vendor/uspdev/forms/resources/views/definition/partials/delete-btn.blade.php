<form action="{{ route('form-definitions.destroy', $formDefinition) }}" method="POST">
  @csrf
  @method('DELETE')
  <button type="submit" class="btn btn-danger btn-sm ml-2"
    onclick="return confirm('Tem certeza que deseja excluir esta definição?')">Excluir</button>
</form>
