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
                    <div class="fw-semibold mb-1">
                        <strong>Ação:</strong> {{ $itemHistorico['action'] }}
                    </div>
                    <div class="small text-muted mb-1">
                        {{ $itemHistorico['created_at'] ?? 'Data não informada' }}
                    </div>
                    <div class="small mb-1">
                        <strong>De:</strong> 
                            @foreach($itemHistorico['froms'] as $from)
                                {{ $from }} 
                                @if(!$loop->last), @endif
                            @endforeach
                        <strong>Para:</strong> 
                            @foreach($itemHistorico['tos'] as $to)
                                {{ $to }}
                                @if(!$loop->last), @endif
                            @endforeach
                    </div>
                    <div class="small mb-1">
                        <strong>Feito por:</strong> 
                        {{ $itemHistorico['user_codpes'] }}
                    </div>
                    <div class="small mb-1">
                        <strong>Detalhes:</strong> 
                        {{ $itemHistorico['metadata'] ?? 'Não informados' }}
                    </div>
                </div>
            @empty
                <p class="text-muted mb-0">Nenhuma movimentação registrada para esta solicitação.</p>
            @endforelse
        </div>
    </div>
</div>
