<form
  action="{{ route('form-submissions.destroy', ['formDefinition' => $definition, 'formSubmission' => $submission]) }}"
  method="POST" style="display:inline;">
  @csrf
  @method('DELETE')
  <button type="submit" class="btn btn-sm btn-outline-danger"
    onclick="return confirm('Tem certeza de que deseja excluir esta submissão?')">
    <i class="fas fa-trash-alt"></i>
  </button>
</form>
