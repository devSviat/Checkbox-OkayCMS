"use strict";

$(function(){
    $(document).on('click', '.fn-sviat-checkbox-action-shift', function(e){
        e.preventDefault();
        let $button = $(this),
            link = $button.attr('href') || $button.data('href'),
            id = typeof $button.data('id') !== 'undefined' ? $button.data('id') : '',
            isPrint = typeof $button.data('print') !== 'undefined';

        $button.addClass('sviat__checkbox_status_button--loading');
        $button.find('.sviat__checkbox_status_loader').removeClass('hidden');
        $button.prop('disabled', true);

        $.ajax({
            url: link,
            dataType: 'json',
            method: 'POST',
            data: {id: id},
        }).done(function(response){
            // Кнопку повертає до тями лише той шлях, що не веде до
            // перезавантаження: інакше вона встигає прийняти другий клік.
            function releaseShiftButton() {
                $button.removeClass('sviat__checkbox_status_button--loading');
                $button.find('.sviat__checkbox_status_loader').addClass('hidden');
                $button.prop('disabled', false);
            }

            if(response.message) {
                releaseShiftButton();
                toastr.error(response.message);
            } else {
                if($button.data('replace') && response.html) {
                    $($button.data('replace')).html(response.html);
                } else if(response.html) {
                    // Замінюємо весь рядок таблиці, якщо є HTML відповідь
                    const $row = $button.closest('tr.sviat__checkbox_receipts_tr');
                    if($row.length) {
                        releaseShiftButton();
                        $row.replaceWith(response.html);
                        toastr.success('Зміну успішно оновлено');
                    } else {
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    }
                } else {
                    if(response.link) {
                        releaseShiftButton();
                        if(isPrint) {
                            let windowPrint = window.open(response.link);
                            windowPrint.print();
                        } else {
                            window.open(response.link);
                        }
                    } else {
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    }
                }
            }
        }).fail(function(xhr, ajaxOptions, thrownError){
            $button.removeClass('sviat__checkbox_status_button--loading');
            $button.find('.sviat__checkbox_status_loader').addClass('hidden');
            $button.prop('disabled', false);

            toastr.error(thrownError || xhr.statusText || 'Невідома помилка');
            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        });
    });

    $(document).on('click', '.fn-checkbox-create-receipt', function(e){
        let $button = $(this);
        
        // Перевіряємо чи кнопка вже заблокована (запобігаємо подвійному кліку)
        if ($button.prop('disabled') || $button.hasClass('sviat__checkbox_status_button--loading')) {
            e.preventDefault();
            return false;
        }
        
        let link = $button.data('href'),
            orderId = $button.data('order_id'),
            isReturn = $button.data('return');

        // Блокуємо кнопку одразу
        $button.prop('disabled', true);
        $button.addClass('sviat__checkbox_status_button--loading');
        $button.find('.sviat__checkbox_status_loader').removeClass('hidden');
        $button.find('.sviat__checkbox_receipt_button_text').css('opacity', '0.3');

        $.ajax({
            url: link,
            dataType: 'json',
            method: 'POST',
            data: {orderId: orderId, isReturn: isReturn},
        }).done(function(response){
            if(response.message) {
                // Розблоковуємо лише на помилці. На успіху кнопка лишається
                // заблокованою до перезавантаження: інакше пів секунди до reload
                // приймають другий клік, а це другий фіскальний документ.
                $button.removeClass('sviat__checkbox_status_button--loading');
                $button.find('.sviat__checkbox_status_loader').addClass('hidden');
                $button.find('.sviat__checkbox_receipt_button_text').css('opacity', '1');
                $button.prop('disabled', false);
                toastr.error(response.message);
            } else {
                setTimeout(function() {
                    window.location.reload();
                }, 500);
            }
        }).fail(function(xhr, ajaxOptions, thrownError){
            $button.removeClass('sviat__checkbox_status_button--loading');
            $button.find('.sviat__checkbox_status_loader').addClass('hidden');
            $button.find('.sviat__checkbox_receipt_button_text').css('opacity', '1');
            $button.prop('disabled', false);

            toastr.error(thrownError || xhr.statusText || 'Невідома помилка');
            console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
        });

    });

    // Custom dropdown для actions
    $(document).on('click', '.sviat__checkbox_actions_btn', function(e){
        e.preventDefault();
        e.stopPropagation();
        const $dropdown = $(this).closest('.sviat__checkbox_actions_dropdown');
        const isOpen = $dropdown.hasClass('open');
        
        // Закриваємо всі інші dropdown
        $('.sviat__checkbox_actions_dropdown').removeClass('open');
        
        // Відкриваємо/закриваємо поточний
        if (!isOpen) {
            $dropdown.addClass('open');
        }
    });

    // Закриваємо dropdown при кліку поза ним
    $(document).on('click', function(e){
        if (!$(e.target).closest('.sviat__checkbox_actions_dropdown').length) {
            $('.sviat__checkbox_actions_dropdown').removeClass('open');
        }
    });

    function checkboxSubmit($button, data) {
        if ($button.prop('disabled')) { return; }

        $button.prop('disabled', true).addClass('sviat__checkbox_status_button--loading');
        $button.find('.sviat__checkbox_status_loader').removeClass('hidden');
        $button.find('.sviat__checkbox_receipt_button_text').css('opacity', '0.3');

        function release() {
            $button.prop('disabled', false).removeClass('sviat__checkbox_status_button--loading');
            $button.find('.sviat__checkbox_status_loader').addClass('hidden');
            $button.find('.sviat__checkbox_receipt_button_text').css('opacity', '1');
        }

        $.ajax({url: $button.data('href'), dataType: 'json', method: 'POST', data: data})
            .done(function(response){
                if (response && response.message) {
                    release();
                    toastr.error(response.message);
                } else {
                    // Без release(): на успіху кнопка лишається заблокованою до
                    // перезавантаження, інакше другий клік у вікні до reload
                    // створить другий фіскальний документ.
                    setTimeout(function(){ window.location.reload(); }, 500);
                }
            })
            .fail(function(xhr, ajaxOptions, thrownError){
                release();
                toastr.error(thrownError || xhr.statusText || 'Невідома помилка');
                console.log(thrownError + "\r\n" + xhr.statusText + "\r\n" + xhr.responseText);
            });
    }

    $(document).on('click', '.fn-checkbox-prepayment', function(){
        let $button = $(this),
            $wrap = $button.closest('.sviat__checkbox_paths'),
            // Тексти помилок лежать на самій формі, а не на зовнішньому
            // контейнері: кнопка стоїть поруч із формою, а не всередині неї.
            $form = $wrap.find('.sviat__checkbox_prepayment_form'),
            amount = $form.find('.fn-checkbox-advance-amount').val();

        // Перший клік лише розкриває поля: аванс — рідкісний сценарій, і поки
        // менеджер його не обрав, два порожні поля на картці лише заважають.
        if ($form.hasClass('sviat__checkbox_prepayment_form--collapsed')) {
            $form.removeClass('sviat__checkbox_prepayment_form--collapsed');
            // selectpicker малює свій список лише коли елемент уже видимий.
            if ($.fn.selectpicker) {
                $form.find('.selectpicker').selectpicker('render');
            }
            $form.find('.fn-checkbox-advance-amount').trigger('focus');
            return;
        }

        let parsedAmount = parseFloat(String(amount).replace(',', '.'));
        // isNaN окремо: будь-який текст дає NaN, а NaN <= 0 хибне, тож «abc»
        // проходило перевірку і летіло на сервер нулем.
        if (!amount || isNaN(parsedAmount) || parsedAmount <= 0) {
            toastr.error($form.data('amount_error') || 'Вкажіть суму авансу числом');
            return;
        }

        // Перший пункт списку порожній навмисне, щоб джерело обирали свідомо:
        // його мітка друкується в чеку, і мовчазний дефолт означав би хибний
        // реквізит у фіскальному документі.
        // Саме select: selectpicker копіює класи елемента на свою обгортку-div,
        // і .val() без тега бере її, а не поле — завжди порожнє значення.
        let source = $form.find('select.fn-checkbox-advance-source').val();
        if (!source) {
            toastr.error($form.data('source_error') || 'Оберіть джерело коштів');
            return;
        }

        checkboxSubmit($button, {
            orderId: $button.data('order_id'),
            amount: amount,
            source: source
        });
    });

    $(document).on('click', '.fn-checkbox-after-payment', function(){
        let $button = $(this),
            $wrap = $button.closest('.sviat__checkbox_prepayment_form'),
            source = $wrap.find('select.fn-checkbox-after-payment-source').val();

        if (!source) {
            toastr.error($wrap.data('source_error') || 'Оберіть джерело коштів');
            return;
        }

        // Порожня сума означає «закрити борг повністю» — решту рахує Checkbox.
        checkboxSubmit($button, {orderId: $button.data('order_id'), source: source, amount: ''});
    });

    $(document).on('click', '.fn-checkbox-refresh-chain', function(){
        let $button = $(this);
        checkboxSubmit($button, {orderId: $button.data('order_id')});
    });

    $(document).on('click', '.fn-checkbox-return-chain', function(){
        let $button = $(this);
        if (!confirm($button.data('confirm') || 'Повернути весь ланцюжок чеків цього замовлення?')) { return; }
        checkboxSubmit($button, {orderId: $button.data('order_id')});
    });

});
