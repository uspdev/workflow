 @can('admin')
     <h4 class="mb-3 pt-4">Todas as Transições (Administrador):</h4>

     <div class="d-flex flex-wrap {{ count($workflowObjectData['forms']) < 1 ? 'input-group' : 'btn-group' }}" role="group">
         @foreach ($workflowObjectData['workflowTransitions']['all'] as $trans)
             @if (!$trans->form)
                 <form action="{{ route('workflows.applyTransition', $workflowObjectData['workflowObject']->id) }}"
                     method="POST" class="d-inline d-flex">
                     @csrf
                     <input type="hidden" name="transition" value="{{ $trans->name }}">
                     <input type="hidden" name="workflowDefinitionName"
                         value="{{ $workflowObjectData['workflowDefinition']->definition['name'] }}">
             @endif
             <button type="submit" data-transition="{{ $trans->name }}"
                 @if (!$trans->form) data-url="{{ route('workflows.applyTransition', $workflowObjectData['workflowObject']->id) }}"
                    data-workflow="{{ $workflowObjectData['workflowDefinition']->definition['name'] }}" @endif
                 class="m-1 btn transition-btn rounded
                    {{ !in_array($trans->name, $workflowObjectData['workflowTransitions']['enabledNames']) || !in_array($trans->name, $workflowObjectData['workflowTransitions']['allowedNames']) ? 'btn-secondary' : 'btn-primary'  }}"
                 {{ !in_array($trans->name, $workflowObjectData['workflowTransitions']['allowedNames']) ? 'disabled' : '' }}>
                 {{ $trans->label }}
             </button>

             @if (!$trans->form)
                 </form>
             @endif
         @endforeach
     </div>
 @endcan
