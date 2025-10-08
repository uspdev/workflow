<form action="{{ route('form-definitions.destroy', $formDefinition) }}" method="POST">
  @csrf
  @method('DELETE')
  <input type="hidden" name="destroy_trashed" value="1">
  <button type="submit" class="btn btn-danger btn-sm ml-2"
    onclick="return confirm('Tem certeza que deseja limpar os registros excluídos? Não poderá ser revertido!')">Limpar registros excluídos</button>
</form>
