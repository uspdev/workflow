{{-- Botões de ação disponíveis para o usuário no estado atual.
     Usa workflowObjectData['transicoesVisiveis'] preparado no controller. --}}
<h4><strong>Selecionar Ação:</strong></h4>
@forelse ($workflowObjectData['workflowTransitions']['allowed'] as $allwdTrans)
    <button type="submit"
        data-transition="{{ $allwdTrans->name }}"
        data-url="{{ route('workflows.applyTransition', $workflowObjectData['workflowObject']->id) }}"
        data-workflow="{{ $workflowObjectData['workflowDefinition']->definition['name'] }}"
        class="m-1 btn transition-btn rounded btn-primary">
        {{ $allwdTrans->label }}
    </button>
    @if(!$loop->last) | @endif
@empty
    <p class="text-muted">Nenhuma ação disponível.</p>
@endforelse
