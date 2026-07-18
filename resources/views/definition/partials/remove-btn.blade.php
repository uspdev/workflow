<form action="{{ route('workflows.destroyDefinition', [
    'definitionName' => $workflowDefinition->name,
    'version' => $workflowDefinition->version
]) }}" method="POST" onsubmit="return confirm('Tem certeza que deseja remover esta definição ?');">

    @csrf
    @method('DELETE')

    <button type="submit" class="btn btn-danger btn-sm mr-1 ml-1">
        Remover
    </button>
</form>