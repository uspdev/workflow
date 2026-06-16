{{-- Histórico de estados da solicitação voltado ao usuário comum.
     Exibe título da ação, data e descrição amigável sem dados sensíveis.
     Usa workflowObjectData['historicoEstados'] preparado no controller. --}}
<div class="col-12 col-xl-4 mt-3 mt-xl-0">
    <div class="card h-100">
        <div class="card-header">
            <span class="h5 mb-0">Histórico de Estados</span>
        </div>
        <div class="card-body">
            @forelse ($workflowObjectData['historicoEstados'] ?? [] as $itemHistorico)
                <div class="border rounded px-3 py-2 mb-2 bg-light">
                    <div class="fw-semibold mb-1">{{ $itemHistorico['titulo'] }}</div>
                    <div class="small text-muted mb-1">
                        {{ $itemHistorico['created_at']
                            ? \Carbon\Carbon::parse($itemHistorico['created_at'])->format('d/m/Y H:i')
                            : 'Data não informada' }}
                    </div>
                    <div class="small">{{ $itemHistorico['detalhe'] }}</div>
                </div>
            @empty
                <p class="text-muted mb-0">Nenhuma movimentação registrada para esta solicitação.</p>
            @endforelse
        </div>
    </div>
</div>
