{{-- Botões de ação disponíveis para o usuário no estado atual.
     Usa workflowObjectData['transicoesVisiveis'] preparado no controller. --}}
@foreach ($workflowObjectData['transicoesVisiveis'] as $chaveEstado => $transicoes)
    <h4><strong>Selecionar Ação:</strong></h4>
    @forelse ($transicoes as $nomeTransicao => $dadosTransicao)
        <button type="submit"
            data-transition="{{ $nomeTransicao }}"
            data-url="{{ route('workflows.applyTransition', $workflowObjectData['workflowObject']->id) }}"
            data-workflow="{{ $workflowObjectData['workflowDefinition']->definition['name'] }}"
            class="m-1 btn transition-btn rounded btn-primary">
            {{ $dadosTransicao['label'] }}
        </button>
        @notLast('|')
    @empty
        <p class="text-muted">Nenhuma ação disponível.</p>
    @endforelse
@endforeach
