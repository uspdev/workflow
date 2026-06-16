{{-- Container oculto com os formulários de cada transição disponível.
     Revelado via JS quando o usuário clica no botão da transição correspondente. --}}
@if (count($workflowObjectData['forms']) > 0)
    <div class="card mt-3" id="transition-forms-container" style="display: none;">
        <div class="card-body">
            <h4 class="mb-3">Formulário da Transição</h4>
            @foreach ($workflowObjectData['forms'] as $formulario)
                <div class="card mb-3 inline-transition-form d-none"
                    data-transition="{{ $formulario['transition'] }}">
                    <div class="card-header">
                        Transição: <strong>@include('show.partials.transition-label')</strong>
                    </div>
                    <div class="card-body">
                        {!! $formulario['html'] !!}
                    </div>
                </div>
            @endforeach
        </div>
    </div>
@endif
