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
            $button.removeClass('sviat__checkbox_status_button--loading');
            $button.find('.sviat__checkbox_status_loader').addClass('hidden');
            $button.prop('disabled', false);
            
            if(response.message) {
                toastr.error(response.message);
            } else {
                if($button.data('replace') && response.html) {
                    $($button.data('replace')).html(response.html);
                } else if(response.html) {
                    // Замінюємо весь рядок таблиці, якщо є HTML відповідь
                    const $row = $button.closest('tr.sviat__checkbox_receipts_tr');
                    if($row.length) {
                        $row.replaceWith(response.html);
                        toastr.success('Зміну успішно оновлено');
                    } else {
                        setTimeout(function() {
                            window.location.reload();
                        }, 500);
                    }
                } else {
                    if(response.link) {
                        if(isPrint) {
                            let windowPrint = window.open(response.link);
                            windowPrint.print();
                        } else {
                            if (typeof $.fancybox !== 'undefined') {
                                $.fancybox.open({
                                    src: response.link,
                                    type : 'iframe'
                                });
                            } else {
                                window.open(response.link);
                            }
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
            $button.removeClass('sviat__checkbox_status_button--loading');
            $button.find('.sviat__checkbox_status_loader').addClass('hidden');
            $button.find('.sviat__checkbox_receipt_button_text').css('opacity', '1');
            $button.prop('disabled', false);

            if(response.message) {
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


});
