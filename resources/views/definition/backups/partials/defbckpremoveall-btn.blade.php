<a href="{{ route('workflows.def-rmv-bckps',['workflowDefinition' => $workflowDefinition]) }}" class="btn btn-sm btn-danger ml-2" onclick="return confirm('Tem certeza de que quer remover todos os backups de {{ $workflowDefinition->name }} ?')">
  Remover todos os backups da definição
</a>
