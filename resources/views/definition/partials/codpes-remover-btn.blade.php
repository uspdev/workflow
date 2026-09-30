<button class="remover-codpes-btn btn btn-sm py-0" data-codpes="{{ $codpes }}">
  <i class="fas fa-trash text-danger"></i>
</button>
<input type="hidden" name="codpes_rem" value="0">

@pushOnce('scripts')
  <script>
    $(document).ready(function() {

      $('.remover-codpes-btn').on('click', function() {
        if (confirm('Tem certeza?')) {
          $(':input[name=codpes_rem]').val($(this).data('codpes'))
        } else {
          return false
        }
      })

    })
  </script>
@endpushOnce
