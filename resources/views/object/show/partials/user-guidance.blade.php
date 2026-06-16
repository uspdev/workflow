{{-- Painel de orientação ao usuário: exibe status atual da solicitação,
     mensagem explicativa e ações disponíveis quando aplicável. --}}
@php $orientacao = $workflowObjectData['orientacaoUsuario'] ?? null; @endphp

@if ($orientacao)
    <div class="alert alert-{{ $orientacao['variante'] }}" role="alert">
        <h5 class="alert-heading mb-2">{{ $orientacao['titulo'] }}</h5>
        <p class="mb-2">{{ $orientacao['mensagem'] }}</p>

        @if (!empty($orientacao['estadosAtuais']))
            <div class="small text-muted mb-1">Estado atual</div>
            <div class="mb-2 d-flex flex-wrap gap-2">
                @foreach ($orientacao['estadosAtuais'] as $descricaoEstado)
                    <span class="badge text-bg-light border">{{ $descricaoEstado }}</span>
                @endforeach
            </div>
        @endif

        @if (!empty($orientacao['acoesDisponiveis']))
            <div class="small text-muted mb-1">Ações disponíveis para você</div>
            <ul class="mb-0">
                @foreach ($orientacao['acoesDisponiveis'] as $rotuloAcao)
                    <li>{{ $rotuloAcao }}</li>
                @endforeach
            </ul>
        @endif
    </div>
@endif
