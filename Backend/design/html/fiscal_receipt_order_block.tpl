{if $order->id}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="heading_box">
                {$btr->sviat__checkbox__order_receipts|escape}
                <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                    <a class="btn-minimize" href="javascript:;"><i class="fa fn_icon_arrow fa-angle-down"></i></a>
                </div>
            </div>
            <div class="toggle_body_wrap on fn_card">
                <div class="sviat__checkbox_receipts_block">
                    {if $orderReceipts|count}
                        <div class="sviat__checkbox_table_wrapper mb-h">
                            <table class="sviat__checkbox_receipts_table">
                                <thead>
                                    <tr>
                                        <th>{$btr->sviat__checkbox__table_type|escape}</th>
                                        <th>{$btr->sviat__checkbox__table_datetime|escape}</th>
                                        <th>{$btr->sviat__checkbox__table_id|escape}</th>
                                        <th style="width: 60px; min-width: 60px;">{$btr->sviat__checkbox__table_actions|escape}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach $orderReceipts as $orderReceipt}
                                        <tr class="sviat__checkbox_receipts_tr">
                                            <td>
                                                {if $orderReceipt->receipt_type == 'prepayment'}
                                                    <span class="sviat__checkbox_tag sviat__checkbox_tag--sale">{$btr->sviat__checkbox__receipt_type_prepayment|escape}</span>
                                                {elseif $orderReceipt->receipt_type == 'after_payment'}
                                                    <span class="sviat__checkbox_tag sviat__checkbox_tag--sale">{$btr->sviat__checkbox__receipt_type_after_payment|escape}</span>
                                                {elseif $orderReceipt->receipt_type == 'return' || $orderReceipt->is_return}
                                                    <span class="sviat__checkbox_tag sviat__checkbox_tag--return">{$btr->sviat__checkbox__receipt_type_return|escape}</span>
                                                {else}
                                                    <span class="sviat__checkbox_tag sviat__checkbox_tag--sale">{$btr->sviat__checkbox__receipt_type_sale|escape}</span>
                                                {/if}
                                                {if $orderReceipt->is_test}
                                                    <span class="sviat__checkbox_tag sviat__checkbox_tag--test">{$btr->sviat__checkbox__receipt_tag_test|escape}</span>
                                                {/if}
                                            </td>

                                            <td>
                                                {$orderReceipt->created_at|date_format:"d.m.Y H:i:s"|default:"—"}
                                            </td>

                                            <td>
                                                {if $orderReceipt->receipt_id}
                                                    <a href=""
                                                        class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile sviat__checkbox_receipt_id_value"
                                                        data-hint="Click to copy" data-hint-copied="✔ Copied to clipboard">
                                                        {$orderReceipt->receipt_id|escape}
                                                    </a>
                                                {else}
                                                    —
                                                {/if}
                                            </td>

                                            <td class="sviat__checkbox_receipts_actions_cell">
                                                {if $orderReceipt->receipt_id}
                                                    <div class="sviat__checkbox_actions_dropdown">
                                                        <button type="button" class="sviat__checkbox_actions_btn" title="{$btr->sviat__checkbox__table_actions|escape}">
                                                            <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20"
                                                                viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
                                                                stroke-linecap="round" stroke-linejoin="round">
                                                                <path stroke="none" d="M0 0h24v24H0z" fill="none" />
                                                                <path d="M4 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                                                <path d="M11 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                                                <path d="M18 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0" />
                                                            </svg>
                                                        </button>
                                                        <ul class="sviat__checkbox_actions_menu">
                                                            <li><a href="https://api.checkbox.ua/api/v1/receipts/{$orderReceipt->receipt_id|escape}/pdf"
                                                                    target="_blank">PDF</a></li>
                                                            <li><a href="https://api.checkbox.ua/api/v1/receipts/{$orderReceipt->receipt_id|escape}/html?show_buttons=true"
                                                                    target="_blank">HTML</a></li>
                                                            <li><a href="https://api.checkbox.ua/api/v1/receipts/{$orderReceipt->receipt_id|escape}/text"
                                                                    target="_blank">TEXT</a></li>
                                                        </ul>
                                                    </div>
                                                {else}
                                                    —
                                                {/if}
                                            </td>
                                        </tr>
                                    {/foreach}
                                </tbody>
                            </table>
                        </div>
                        <script>
                            sclipboard();
                        </script>
                    {else}
                        <div class="sviat__checkbox_alert sviat__checkbox_alert--info">
                            <div class="sviat__checkbox_alert__title">{$btr->sviat__checkbox__no_receipts_title|escape}</div>
                            <div class="sviat__checkbox_alert__details">{$btr->sviat__checkbox__no_receipts_details|escape}</div>
                        </div>
                    {/if}

                    {if $checkboxChain}
                        <div class="sviat__checkbox_alert {if $checkboxChain.pre_payment_status == 'unknown'}sviat__checkbox_alert--warning{else}sviat__checkbox_alert--info{/if}">
                            <div class="sviat__checkbox_alert__title">
                                {$btr->sviat__checkbox__chain_title|escape}: {$checkboxChainStatusText|escape}
                            </div>
                            {if $checkboxChain.pre_payment_status != 'unknown'}
                                <div class="sviat__checkbox_alert__details">
                                    {$btr->sviat__checkbox__chain_paid|escape}
                                    {($checkboxChain.paid_sum/100)|string_format:"%.2f"}
                                    {$btr->sviat__checkbox__chain_of|escape}
                                    {($checkboxChain.total_sum/100)|string_format:"%.2f"} {$btr->sviat__checkbox__chain_uah|escape},
                                    {$btr->sviat__checkbox__chain_left|escape}
                                    {($checkboxChain.left_to_pay/100)|string_format:"%.2f"} {$btr->sviat__checkbox__chain_uah|escape}
                                </div>
                            {/if}
                            {if $checkboxChainNextStep}
                                <div class="sviat__checkbox_alert__next">{$checkboxChainNextStep|escape}</div>
                            {/if}
                            {* Стан лежить у нас, тож ланцюжок, змінений ззовні, наздоганяє
                               саме ця кнопка — інакше довелось би чекати добової звірки. *}
                            <button type="button" class="btn btn_small btn_checkbox-refresh fn-checkbox-refresh-chain"
                                    data-order_id="{$order->id}"
                                    data-href="{url_generator route="Sviat_Checkbox_refreshChainStatus" absolute=1}">
                                <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__button_refresh_chain|escape}</span>
                                <span class="sviat__checkbox_status_loader hidden"><span class="spinner"></span></span>
                            </button>
                        </div>
                    {/if}

                    <div class="sviat__checkbox_receipts_footer mb-1">
                        {if !$checkboxActiveShift}
                            <div class="sviat__checkbox_alert sviat__checkbox_alert--warning">
                                <div class="sviat__checkbox_alert__title">{$btr->sviat__checkbox__alert_no_shift_title|escape}</div>
                                <div class="sviat__checkbox_alert__details">{$btr->sviat__checkbox__errors_no_shift}</div>
                            </div>
                        {elseif $emptyOrderReceiptsCount}
                            <div class="sviat__checkbox_alert sviat__checkbox_alert--warning">
                                <div class="sviat__checkbox_alert__title">{$btr->sviat__checkbox__alert_unfinished_title|escape}</div>
                                <div class="sviat__checkbox_alert__details">{$btr->sviat__checkbox__errors_empty_receipts_isset}</div>
                            </div>
                        {else}
                            {* «Не відправляти» стосується основної суми замовлення, а не
                               авансу: той може надійти іншим шляхом, і менеджер називає
                               його явно. Тому попередження лишається, а дії ланцюжка — ні. *}
                            {if $orderCheckboxDontSend}
                                <div class="sviat__checkbox_alert sviat__checkbox_alert--warning">
                                    <div class="sviat__checkbox_alert__title">{$btr->sviat__checkbox__alert_dont_send_title|escape}</div>
                                    <div class="sviat__checkbox_alert__details">{$btr->sviat__checkbox__payment_method_dont_send|escape}</div>
                                    {if $checkboxChain}
                                        <div class="sviat__checkbox_alert__next">{$btr->sviat__checkbox__chain_open_despite_skip|escape}</div>
                                    {elseif $checkboxActions.prepayment}
                                        <div class="sviat__checkbox_alert__next">{$btr->sviat__checkbox__sale_hidden_by_skip|escape}</div>
                                    {elseif $checkboxActions.prepaymentHiddenByPaid}
                                        {* Інакше тут лишається саме попередження й порожнє місце
                                           під ним: обидва шляхи закриті, і жоден не пояснено. *}
                                        <div class="sviat__checkbox_alert__next">{$btr->sviat__checkbox__advance_hidden_by_paid|escape}</div>
                                    {/if}
                                </div>
                            {/if}
                            <div class="sviat__checkbox_receipts_actions">
                                {if $checkboxIsTestCashier}
                                    <div class="sviat__checkbox_test_notice">{$btr->sviat__checkbox__test_mode_notice|escape}</div>
                                {/if}
                                {* Пояснення вже стоїть у блоці стану ланцюжка вище — тут
                                   лишається порожньо, щоб не повторювати те саме двічі. *}

                                {* Два шляхи ведуть до різних наслідків — один чек проти ланцюжка
                                   з двох, — і вибір незворотний. Тому вони розділені підписами,
                                   а не стоять поруч однаковими кнопками. *}
                                {* Один рядок пояснення на обидва шляхи: різницю задають самі
                                   назви кнопок, а поля авансу з'являються лише коли його обрали. *}
                                {if $checkboxActions.prepayment}
                                    <div class="sviat__checkbox_paths">
                                        <div class="sviat__checkbox_paths__buttons">
                                            {if $checkboxActions.sale}
                                                <button type="button" class="btn btn_small btn_checkbox fn-checkbox-create-receipt"
                                                    data-order_id="{$order->id}" data-return="0"
                                                    data-href="{url_generator route="Sviat_Checkbox_createReceipt" absolute=1}">
                                                    <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__order_receipt_create|escape}</span>
                                                    <span class="sviat__checkbox_status_loader hidden"><span class="spinner"></span></span>
                                                </button>
                                            {/if}
                                            {* Аванс питає суму й джерело — обидва йдуть у фіскальний
                                               документ, тож питаємо їх у модалці, з явним підтвердженням,
                                               а не двома полями, які постійно висять на картці. *}
                                            <button type="button" class="btn btn_small btn_checkbox-advance hint-bottom-middle-t-white-s-small-mobile hint-anim"
                                                    data-hint="{$btr->sviat__checkbox__tip_prepayment|escape}"
                                                    data-toggle="modal" data-target="#sviat_checkbox_advance_modal">
                                                <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__button_prepayment|escape}</span>
                                            </button>
                                        </div>
                                    </div>
                                {/if}

                                {if $checkboxActions.afterPayment}
                                    {* Решта може надійти не тим шляхом, що аванс, — саме заради
                                       цього й потрібен ланцюжок. Тому джерело обирається, а не
                                       виводиться зі способу оплати; підставлене значення лише
                                       найімовірніше, і менеджер може його змінити. *}
                                    <div class="sviat__checkbox_prepayment_form" data-source_error="{$btr->sviat__checkbox__errors_source_not_chosen|escape}">
                                            <select class="form-control selectpicker fn-checkbox-after-payment-source"
                                                    aria-label="{$btr->sviat__checkbox__advance_source_label|escape}">
                                                {foreach $checkboxSources as $checkboxSource}
                                                    <option value="{$checkboxSource.key|escape}"{if $checkboxSource.key == $checkboxAfterPaymentSource} selected{/if}>{$checkboxSource.label|escape}</option>
                                                {/foreach}
                                            </select>
                                            <button type="button" class="btn btn_small btn_checkbox fn-checkbox-after-payment hint-bottom-middle-t-white-s-small-mobile hint-anim"
                                                    data-hint="{$btr->sviat__checkbox__tip_after_payment|escape}"
                                                    data-order_id="{$order->id}"
                                                    data-href="{url_generator route="Sviat_Checkbox_createAfterPaymentReceipt" absolute=1}">
                                                <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__button_after_payment|escape}</span>
                                                <span class="sviat__checkbox_status_loader hidden"><span class="spinner"></span></span>
                                            </button>
                                    </div>
                                {/if}

                                {if $checkboxActions.returnChain}
                                    <button type="button" class="btn btn_small btn_checkbox-return fn-checkbox-return-chain fn-checkbox-confirm"
                                            data-toggle="modal" data-target="#sviat_checkbox_confirm_modal"
                                            data-order_id="{$order->id}"
                                            data-confirm_title="{if $checkboxChain.pre_payment_status == 'PARTIAL_PAID'}{$btr->sviat__checkbox__button_return_advance|escape}{else}{$btr->sviat__checkbox__button_return_all|escape}{/if}"
                                            data-confirm_text="{$btr->sviat__checkbox__confirm_return_chain|escape}"
                                            data-href="{url_generator route="Sviat_Checkbox_returnChain" absolute=1}">
                                        {* Назва за станом: при відкритому ланцюжку повертається лише аванс, при
                                               закритому — ще й чек післяплати. «Повернути ланцюжок»
                                               не казало менеджеру ні того, ні того. *}
                                            <span class="sviat__checkbox_receipt_button_text">{if $checkboxChain.pre_payment_status == 'PARTIAL_PAID'}{$btr->sviat__checkbox__button_return_advance|escape}{else}{$btr->sviat__checkbox__button_return_all|escape}{/if}</span>
                                        <span class="sviat__checkbox_status_loader hidden"><span class="spinner"></span></span>
                                    </button>
                                {/if}

                                {* Без вибору між шляхами — просто кнопка, без зайвих підписів. *}
                                {if $checkboxActions.sale && !$checkboxActions.prepayment}
                                    <button type="button" class="btn btn_small btn_checkbox fn-checkbox-create-receipt"
                                        data-order_id="{$order->id}" data-return="0"
                                        data-href="{url_generator route="Sviat_Checkbox_createReceipt" absolute=1}">
                                        <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__order_receipt_create|escape}</span>
                                        <span class="sviat__checkbox_status_loader hidden"><span class="spinner"></span></span>
                                    </button>
                                {/if}

                                {if $checkboxActions.return}
                                    {* Чек повернення скасовує фіскалізацію й незворотний так само, як
                                       і повернення ланцюжка, — питаємо так само. *}
                                    <button type="button" class="btn btn_small btn_checkbox-return fn-checkbox-create-receipt fn-checkbox-confirm"
                                        data-toggle="modal" data-target="#sviat_checkbox_confirm_modal"
                                        data-order_id="{$order->id}" data-return="1"
                                        data-confirm_title="{$btr->sviat__checkbox__order_receipt_create_return|escape}"
                                        data-confirm_text="{$btr->sviat__checkbox__confirm_create_return|escape}"
                                        data-href="{url_generator route="Sviat_Checkbox_createReceipt" absolute=1}">
                                        <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__order_receipt_create_return|escape}</span>
                                        <span class="sviat__checkbox_status_loader hidden"><span class="spinner"></span></span>
                                    </button>
                                {/if}
                            </div>
                        {/if}
                    </div>
                </div>

                {* Модалки блоку. Розмітка й показ — теми: data-toggle="modal"
                   плюс data-target, закриття — data-dismiss. Обидві лежать усередині
                   блоку чеків, бо на сторінці рівно одне замовлення. *}
                <div id="sviat_checkbox_advance_modal" class="modal fade show" role="dialog">
                    <div class="modal-dialog modal-md">
                        <div class="modal-content">
                            <div class="card-header">
                                <div class="heading_modal">{$btr->sviat__checkbox__modal_advance_title|escape}</div>
                            </div>
                            <div class="modal-body"
                                 data-amount_error="{$btr->sviat__checkbox__errors_advance_not_a_number|escape}"
                                 data-source_error="{$btr->sviat__checkbox__errors_source_not_chosen|escape}">
                                <div class="sviat__checkbox_modal_field">
                                    <div class="heading_label">{$btr->sviat__checkbox__advance_amount_placeholder|escape}</div>
                                    <input type="text" class="form-control fn-checkbox-advance-amount"
                                           placeholder="{$btr->sviat__checkbox__advance_amount_placeholder|escape}">
                                </div>
                                <div class="sviat__checkbox_modal_field">
                                    <div class="heading_label">{$btr->sviat__checkbox__advance_source_label|escape}</div>
                                    <select class="form-control selectpicker fn-checkbox-advance-source"
                                            aria-label="{$btr->sviat__checkbox__advance_source_label|escape}">
                                        {* Порожній перший пункт: інакше перше джерело обирається саме,
                                           і його мітка мовчки йде в рядок 19 чека. *}
                                        <option value="">{$btr->sviat__checkbox__advance_source_empty|escape}</option>
                                        {foreach $checkboxSources as $checkboxSource}
                                            <option value="{$checkboxSource.key|escape}">{$checkboxSource.label|escape}</option>
                                        {/foreach}
                                    </select>
                                </div>
                                <div class="sviat__checkbox_modal_actions">
                                    <button type="button" class="btn btn_small btn_gray" data-dismiss="modal">
                                        {$btr->sviat__checkbox__modal_cancel|escape}
                                    </button>
                                    <button type="button" class="btn btn_small btn_checkbox-advance fn-checkbox-prepayment"
                                            data-order_id="{$order->id}"
                                            data-href="{url_generator route="Sviat_Checkbox_createPrepaymentReceipt" absolute=1}">
                                        <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__modal_advance_submit|escape}</span>
                                        <span class="sviat__checkbox_status_loader hidden"><span class="spinner"></span></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div id="sviat_checkbox_confirm_modal" class="modal fade show" role="dialog">
                    <div class="modal-dialog modal-md">
                        <div class="modal-content">
                            <div class="card-header">
                                <div class="heading_modal fn-checkbox-confirm-title"></div>
                            </div>
                            <div class="modal-body">
                                <div class="sviat__checkbox_modal_text fn-checkbox-confirm-text"></div>
                                <div class="sviat__checkbox_modal_actions">
                                    <button type="button" class="btn btn_small btn_gray" data-dismiss="modal">
                                        {$btr->sviat__checkbox__modal_cancel|escape}
                                    </button>
                                    <button type="button" class="btn btn_small btn_checkbox-return fn-checkbox-confirm-yes">
                                        <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__modal_confirm_yes|escape}</span>
                                        <span class="sviat__checkbox_status_loader hidden"><span class="spinner"></span></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}