@if (session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="m-3">   
        @include('uspdev-workflow::object.show.partials.user-guidance')
        @include('uspdev-workflow::object.show.partials.acoes-usuario')
        @include('uspdev-workflow::object.show.partials.todas-transicoes-admin')
        @include('uspdev-workflow::object.show.partials.formularios-transicao')
        @include('uspdev-workflow::object.show.partials.transition-scripts')
    </div>

    <div class="card mt-2">
        <div class="card-body">
            <div class="row">
                <div class="col-12 col-xl-8">
                    @include('uspdev-workflow::object.show.partials.submissoes')
                </div>
                @include('uspdev-workflow::object.show.partials.historico-estados')
            </div>
        </div>
    </div>
</div>