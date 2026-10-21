<button type="button"
        class="remover-codpes-btn btn btn-sm py-0"
        data-codpes="{{ $codpes }}"
        data-wf-id="{{ $wf_id }}">
    <i class="fas fa-trash text-danger"></i>
</button>

@pushOnce('scripts')
<script>
    $(document).ready(function() {
        $('.remover-codpes-btn').on('click', function() {
            if (!confirm('Tem certeza?')) {
                return;
            }

            const form = $('<form>', {
                method: 'POST',
                action: '{{ route("workflows.setuser") }}'
            });

            form.append($('<input>', {
                type: 'hidden',
                name: '_token',
                value: '{{ csrf_token() }}'
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'codpes_rem',
                value: $(this).data('codpes')
            }));

            form.append($('<input>', {
                type: 'hidden',
                name: 'wf_id',
                value: $(this).data('wf-id')
            }));

            $('body').append(form);
            form.trigger('submit');
        });
    });
</script>
@endPushOnce
