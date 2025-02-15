jQuery(document).ready(function ($) {
    // Define a data padrão para amanhã
    function setDefaultPublicityDate() {
    const tomorrow = moment().add(1, 'days').format('DD/MM/YYYY');
    const publicityDateField = $('#date-picker');

    if (publicityDateField.length) {
        publicityDateField.val(tomorrow); // Define a data inicial
        publicityDateField.trigger('change'); // Dispara evento de mudança
        updateFinalDates(); // Atualiza os cálculos imediatamente
    } else {
        console.error('Campo "date-picker" não encontrado ao configurar data padrão.');
    }
}

    // Atualizar as datas finais dinamicamente no Tempo de Duração
    function updateFinalDates() {
        const datePicker = $('#date-picker');
        const timeSlots = $('.time-slot input[type="radio"]');

        const selectedDate = moment(datePicker.val(), 'DD/MM/YYYY');
        if (!selectedDate.isValid()) return;

        timeSlots.each(function () {
            const weeks = parseInt($(this).val().split('-')[0]); // Extrai semanas
            if (isNaN(weeks)) return;

            const finalDate = selectedDate.clone().add(weeks * 7 - 1, 'days'); // Ajuste para refletir até a data final corretamente
            const formattedDate = finalDate.format('DD/MM/YYYY');

            const label = $(this).closest('.time-slot').find('span');
            if (label) {
                label.text(`Até ${formattedDate}`);
            }
        });
    }

    // Atualizar o título do dropdown ao selecionar uma opção
    function updateDropdownTitle(dropdown, value) {
        const title = dropdown.find('.dropdown-toggle');
        if (title) {
            title.text(value);
        }
    }

    function closeDropdown(dropdown) {
        dropdown.removeClass('active');
    }

    function getSelectedOptions() {
        const selectedOptions = {};
        $('.panel-dropdown').each(function () {
            const attribute = $(this).find('input.bookable-service-radio:checked').attr('name');
            const value = $(this).find('input.bookable-service-radio:checked').val();
            if (attribute && value) {
                selectedOptions[attribute] = value;
            }
        });
        return selectedOptions;
    }

    // Validar seleção de opções e habilitar o botão de reserva
    function validateSelections() {
        const reserveButton = $('#youpoint-reserve-now');
        const hasSelection = $('input.bookable-service-radio:checked').length > 0;

        reserveButton.prop('disabled', !hasSelection);
        reserveButton.text(hasSelection ? 'Solicitar Reserva' : 'Selecione pelo menos uma opção');
    }

    function sendAjaxRequest(action, data, successCallback, errorCallback) {
        if (!youpoint_ajax || !youpoint_ajax.ajax_url || !youpoint_ajax.nonce) {
            console.error('Configuração do youpoint_ajax ausente ou incorreta:', youpoint_ajax);
            alert('Erro na configuração. Por favor, atualize a página.');
            return;
        }

        console.log('Enviando dados para AJAX:', data);

        $.ajax({
            url: youpoint_ajax.ajax_url,
            type: 'POST',
            data: {
                action: action,
                ...data,
                nonce: youpoint_ajax.nonce,
            },
            success: function (response) {
                console.log('Resposta AJAX:', response);
                if (response.success) {
                    successCallback(response);
                } else if (errorCallback) {
                    errorCallback(response);
                } else {
                    alert('Erro no servidor: ' + (response.data ? response.data.message : 'Erro desconhecido.'));
                }
            },
            error: function () {
                alert('Erro na comunicação com o servidor. Por favor, tente novamente.');
            },
        });
    }

    function updateButtonLabel() {
        const selectedOptions = getSelectedOptions();
        const productId = $('#form-booking').data('product-id');

        sendAjaxRequest(
            'youpoint_get_variation_price',
            { product_id: productId, selected_options: selectedOptions },
            function (response) {
                const totalPrice = parseFloat(response.data.price) || 0;
                $('span[youpointTotalPrice]').text('R$: ' + totalPrice.toFixed(2));
            },
            function () {
                $('span[youpointTotalPrice]').text('R$: 0.00');
            }
        );
    }

    function handleRadioChange() {
        const dropdown = $(this).closest('.panel-dropdown');
        const selectedLabel = $(this).closest('.time-slot, .single-service').find('strong, h5').text();

        updateDropdownTitle(dropdown, selectedLabel);
        validateSelections();
        updateButtonLabel();
        closeDropdown(dropdown); // Fecha o dropdown após seleção
    }

    function handleReserveClick(e) {
        e.preventDefault();

        const button = $(this);
        const productId = $('#form-booking').data('product-id');
        const selectedOptions = getSelectedOptions();

        // Valida a existência do campo "date-picker"
        const datePickerField = $('#date-picker');
        if (!datePickerField.length) {
            console.error('Campo "Data da Publicidade" não encontrado.');
            alert('Erro: O campo "Data da Publicidade" não está disponível. Por favor, recarregue a página.');
            button.prop('disabled', false).text('Solicitar Reserva');
            return;
        }

        const publicityDate = moment(datePickerField.val(), 'DD/MM/YYYY').format('YYYY-MM-DD');

        // Valida o valor do campo de data
        if (!moment(publicityDate, 'YYYY-MM-DD', true).isValid()) {
            alert('Por favor, insira uma data válida no formato DD/MM/YYYY antes de prosseguir.');
            button.prop('disabled', false).text('Solicitar Reserva');
            return;
        }

        button.prop('disabled', true).text('Verificando...');

        sendAjaxRequest(
            'youpoint_add_to_cart',
            { product_id: productId, selected_options: selectedOptions, 'data-da-publicidade': publicityDate },
            function (response) {
                const messages = {
                    exists: 'Plano já adicionado',
                    added: 'Adicionado com sucesso!',
                    updated: 'Plano Atualizado',
                };
                const colors = {
                    exists: '#000',
                    added: '#19b453',
                    updated: '#1e90ff',
                };

                const status = response.data.status;

                // Atualiza o texto e o estilo do botão de acordo com o status
                button.text(messages[status])
                    .css('background-color', colors[status]);
                setTimeout(() => {
                    button.text('Solicitar Reserva').css('background-color', '').prop('disabled', false);
                }, 3000);
            },
            function () {
                button.text('Solicitar Reserva').prop('disabled', false);
            }
        );
    }

    function initializeWidget() {
        setDefaultPublicityDate();
        validateSelections();

        $('#date-picker').on('change', updateFinalDates); // Atualiza datas ao alterar a data
        $('input.bookable-service-radio').on('change', handleRadioChange);
        $('#youpoint-reserve-now').on('click', handleReserveClick);

        console.log('Widget YouPoint inicializado.');
    }

    initializeWidget();

    // Inicializa o calendário no formato correto
    $('#date-picker').daterangepicker({
        singleDatePicker: true,
        showDropdowns: false,
        autoApply: true,
        minDate: moment().add(1, 'days').format('DD/MM/YYYY'),
        locale: {
            format: 'DD/MM/YYYY',
            daysOfWeek: ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'],
            monthNames: [
                'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
                'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'
            ],
        },
    });
});