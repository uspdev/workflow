<!-- Botão para abrir o modal -->
<button type="button" class="btn btn-sm btn-info" data-toggle="modal" data-target="#ajudaModal">
  Ajuda
</button>

<!-- Modal -->
@section('javascripts_bottom')
<div class="modal fade" id="ajudaModal" tabindex="-1" role="dialog" aria-labelledby="ajudaModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="ajudaModalLabel">
          <i class="fas fa-clipboard-list"></i>
          USPdev Forms > Ajuda
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body">
        {!! md2html(file_get_contents(base_path('vendor/uspdev/forms/readme.md'))) !!}
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
@parent
@endsection
