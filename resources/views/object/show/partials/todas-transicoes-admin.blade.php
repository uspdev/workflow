 @can('admin')
     <h4 class="mb-3 pt-4">Todas as Transições (Administrador):</h4>

     <div class="d-flex flex-wrap {{ count($workflowObjectData['forms']) < 1 ? 'input-group' : 'btn-group' }}" role="group">
         @foreach ($workflowObjectData['transicoesAdmin'] as $nomeTransicao => $transicao)
             @if (!$transicao['temFormulario'])
                 <form action="{{ route('workflows.applyTransition', $workflowObjectData['workflowObject']->id) }}"
                     method="POST" class="d-inline d-flex">
                     @csrf
                     <input type="hidden" name="transition" value="{{ $nomeTransicao }}">
                     <input type="hidden" name="workflowDefinitionName"
                         value="{{ $workflowObjectData['workflowDefinition']->definition['name'] }}">
             @endif

             <button type="submit" data-transition="{{ $nomeTransicao }}"
                 @if (!$transicao['temFormulario']) data-url="{{ route('workflows.applyTransition', $workflowObjectData['workflowObject']->id) }}"
                    data-workflow="{{ $workflowObjectData['workflowDefinition']->definition['name'] }}" @endif
                 class="m-1 btn transition-btn rounded
                    {{ !$transicao['temPermissao'] ? 'btn-secondary' : ($transicao['estaHabilitada'] ? 'btn-primary' : 'btn-secondary') }}"
                 {{ !$transicao['temPermissao'] || !$transicao['estaHabilitada'] ? 'disabled' : '' }}>
                 {{ $transicao['label'] }}
             </button>

             @if (!$transicao['temFormulario'])
                 </form>
             @endif
         @endforeach
     </div>
 @endcan
