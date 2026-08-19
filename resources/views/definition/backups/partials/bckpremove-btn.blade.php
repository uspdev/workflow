<a href="{{ route('workflows.def-remove-bckp',['workflowDefinition' => $workflowDefinition, 'created_time' => $created_time]) }}" class="btn btn-sm btn-danger ml-2" onclick="return confirm('Tem certeza que deseja remover este backup ? ')">
  Remover backup
</a>
