{{-- Seção de submissões de formulário. --}}

<div class="d-flex flex-wrap justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Submissões de Formulário</h4>
    <span class="badge bg-secondary fs-6">
        {{ count($workflowObjectData['formSubmissions']) }}
        {{ count($workflowObjectData['formSubmissions']) === 1 ? 'submissão' : 'submissões' }}
    </span>
</div>
@forelse (collect($workflowObjectData['formSubmissions'])->reverse() as $submissao)    
    <div class="card shadow-sm border-0 mb-3"> 
        <div class="card-header bg-light border-0 py-2">
            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2">
                <div class="fw-semibold text-dark">Submissão #{{ $submissao->id }}</div>
                <small class="text-muted">
                    Criado em:
                    {{ optional($submissao->created_at)->format('d/m/Y H:i') ?? $submissao->created_at }}
                </small>
            </div>
        </div>

        <div class="card-body">
                {!! $submissao->showHtml(true, auth()->user()->can('admin')) !!}
        </div>
    </div>
@empty
    <div class="card border-0 shadow-sm mb-2">
        <div class="card-body">
            <h5 class="mb-0 text-muted">Nenhuma submissão encontrada.</h5>
        </div>
    </div>
@endforelse

