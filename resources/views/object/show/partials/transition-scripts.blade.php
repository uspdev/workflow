{{-- Script de comportamento dos botões de transição.
     Ao clicar em um botão transition-btn:
     - Se existir formulário para a transição: exibe o formulário e rola até ele.
     - Se não houver formulário: aplica a transição via POST/AJAX. --}}
@once
    @section('javascripts_bottom')
        @parent
        <script>
            $(document).ready(function () {
                var objectId = '{{ $workflowObjectData['workflowObject']->id ?? 'novo' }}';

                function storageKey(transitionName) {
                    return 'workflow_form_' + objectId + '_' + transitionName;
                }

                function getTransitionNameFromForm(formElement) {
                    var wrapper = $(formElement).closest('.inline-transition-form');
                    return wrapper.data('transition') || $(formElement).find('input[name="transition"]').val() || '';
                }

                function collectFormData(formElement) {
                    var payload = {};
                    var form = $(formElement);

                    form.find('input, textarea, select').each(function () {
                        var field = $(this);
                        var name = field.attr('name');
                        if (!name || name === '_token' || name === 'workflowObject' || name === 'workflowDefinitionName') {
                            return;
                        }

                        if (field.is(':file')) {
                            return;
                        }

                        if (field.is(':checkbox')) {
                            if (!Object.prototype.hasOwnProperty.call(payload, name)) {
                                payload[name] = [];
                            }
                            if (field.is(':checked')) {
                                payload[name].push(field.val());
                            }
                            return;
                        }

                        if (field.is(':radio')) {
                            if (field.is(':checked')) {
                                payload[name] = field.val();
                            }
                            return;
                        }

                        payload[name] = field.val();
                    });

                    return payload;
                }

                function saveFormState(formElement) {
                    var transitionName = getTransitionNameFromForm(formElement);
                    if (!transitionName) {
                        return;
                    }
                    sessionStorage.setItem(storageKey(transitionName), JSON.stringify(collectFormData(formElement)));
                }

                function restoreFormState(formElement, transitionName) {
                    if (!transitionName) {
                        return;
                    }

                    var raw = sessionStorage.getItem(storageKey(transitionName));
                    if (!raw) {
                        return;
                    }

                    var data;
                    try {
                        data = JSON.parse(raw);
                    } catch (e) {
                        return;
                    }

                    var form = $(formElement);
                    Object.keys(data).forEach(function (name) {
                        var value = data[name];
                        var fields = form.find('[name="' + name + '"]');
                        if (!fields.length) {
                            return;
                        }

                        var first = fields.first();

                        if (first.is(':checkbox')) {
                            var values = Array.isArray(value) ? value : [value];
                            fields.each(function () {
                                var checked = values.indexOf($(this).val()) !== -1;
                                $(this).prop('checked', checked).trigger('change');
                            });
                            return;
                        }

                        if (first.is(':radio')) {
                            fields.each(function () {
                                var checked = $(this).val() == value;
                                $(this).prop('checked', checked).trigger('change');
                            });
                            return;
                        }

                        if (first.is('select[multiple]') && Array.isArray(value)) {
                            first.val(value).trigger('change');
                            return;
                        }

                        first.val(value).trigger('change');
                    });
                }

                $('.inline-transition-form form').each(function () {
                    var form = $(this);
                    form.on('input change', 'input, textarea, select', function () {
                        saveFormState(form);
                    });

                    form.on('click', 'button, a', function () {
                        var buttonText = ($(this).text() || '').toLowerCase();
                        if (buttonText.indexOf('voltar') !== -1 || buttonText.indexOf('anterior') !== -1) {
                            setTimeout(function () {
                                saveFormState(form);
                                restoreFormState(form, getTransitionNameFromForm(form));
                            }, 50);
                        }
                    });
                });

                $('.transition-btn').on('click', function (e) {
                    var transitionName = $(this).data('transition');
                    var transitionUrl  = $(this).data('url');
                    var workflowName   = $(this).data('workflow');
                    var formsContainer = $('#transition-forms-container');
                    var formWrapper    = $('.inline-transition-form[data-transition="' + transitionName + '"]');

                    if (formWrapper.length > 0) {
                        e.preventDefault();

                        var transitionForm = formWrapper.find('form').first();
                        if (transitionForm.length === 0) {
                            return;
                        }

                        $('.inline-transition-form').addClass('d-none');
                        formsContainer.show();
                        formWrapper.removeClass('d-none');

                        if (transitionForm.find('input[name="transition"]').length === 0) {
                            transitionForm.append('<input type="hidden" name="transition" value="' + transitionName + '">');
                        } else {
                            transitionForm.find('input[name="transition"]').val(transitionName);
                        }

                        if (transitionForm.find('input[name="workflowDefinitionName"]').length === 0 && workflowName) {
                            transitionForm.append('<input type="hidden" name="workflowDefinitionName" value="' + workflowName + '">');
                        }

                        restoreFormState(transitionForm, transitionName);

                        $('html, body').animate({ scrollTop: formsContainer.offset().top - 20 }, 200);
                        return;
                    }

                    if (transitionUrl) {
                        e.preventDefault();
                        $.ajax({
                            url: transitionUrl,
                            type: 'POST',
                            data: {
                                _token: '{{ csrf_token() }}',
                                transition: transitionName,
                                workflowDefinitionName: workflowName
                            },
                            success: function () { location.reload(); },
                            error: function (xhr) {
                                alert('Erro ao processar a transição: ' + xhr.responseText);
                            }
                        });
                    }
                });
            });
        </script>
    @endsection
@endonce
