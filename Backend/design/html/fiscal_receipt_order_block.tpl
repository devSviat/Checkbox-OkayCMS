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
                    {$createReceipt = true}

                    {if $orderReceipts|count}
                        <div class="sviat__checkbox_table_wrapper mb-h">
                            <table class="sviat__checkbox_receipts_table">
                                <thead>
                                    <tr>
                                        <th>Тип</th>
                                        <th>Дата/час</th>
                                        <th>ID</th>
                                        <th style="width: 60px; min-width: 60px;">Дії</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {foreach $orderReceipts as $orderReceipt}
                                        <tr class="sviat__checkbox_receipts_tr">
                                            <td>
                                                {if $orderReceipt->is_return}
                                                    <span class="sviat__checkbox_tag sviat__checkbox_tag--return">
                                                        Повернення
                                                    </span>
                                                {else}
                                                    <span class="sviat__checkbox_tag sviat__checkbox_tag--sale">
                                                        Продаж
                                                    </span>
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
                                                        <button type="button" class="sviat__checkbox_actions_btn" title="Дії">
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
                        {if $orderReceipts|count}
                            {foreach $orderReceipts as $orderReceipt}
                                {if $orderReceipt@last && !$orderReceipt->is_return}
                                    {$createReceipt = false}
                                {/if}
                            {/foreach}
                        {/if}
                    {else}
                        <div class="sviat__checkbox_alert sviat__checkbox_alert--info">
                            <div class="sviat__checkbox_alert__title">Чеків ще немає</div>
                            <div class="sviat__checkbox_alert__details">Створіть чек нижче — він зʼявиться у цьому списку.</div>
                        </div>
                    {/if}

                    <div class="sviat__checkbox_receipts_footer mb-1">
                        {if !$checkboxActiveShift}
                            <div class="sviat__checkbox_alert sviat__checkbox_alert--warning">
                                <div class="sviat__checkbox_alert__title">Немає активної зміни</div>
                                <div class="sviat__checkbox_alert__details">{$btr->sviat__checkbox__errors_no_shift}</div>
                            </div>
                        {elseif $emptyOrderReceiptsCount}
                            <div class="sviat__checkbox_alert sviat__checkbox_alert--warning">
                                <div class="sviat__checkbox_alert__title">Є незавершені чеки</div>
                                <div class="sviat__checkbox_alert__details">{$btr->sviat__checkbox__errors_empty_receipts_isset}</div>
                            </div>
                        {elseif $orderCheckboxDontSend}
                            <div class="sviat__checkbox_alert sviat__checkbox_alert--warning">
                                <div class="sviat__checkbox_alert__title">Чек не відправляється</div>
                                <div class="sviat__checkbox_alert__details">{$btr->sviat__checkbox__payment_method_dont_send|escape}</div>
                            </div>
                        {else}
                            <div class="sviat__checkbox_receipts_actions">
                                <button type="button"
                                    class="btn btn_small btn_checkbox fn-checkbox-create-receipt{if !$createReceipt} hidden{/if}"
                                    data-order_id="{$order->id}" data-return="0"
                                    data-href="{url_generator route="Sviat_Checkbox_createReceipt" absolute=1}">
                                    <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__order_receipt_create|escape}</span>
                                    <span class="sviat__checkbox_status_loader hidden">
                                        <span class="spinner"></span>
                                    </span>
                                </button>

                                <button type="button"
                                    class="btn btn_small btn_checkbox-return fn-checkbox-create-receipt{if $createReceipt} hidden{/if}"
                                    data-order_id="{$order->id}" data-return="1"
                                    data-href="{url_generator route="Sviat_Checkbox_createReceipt" absolute=1}">
                                    <span class="sviat__checkbox_receipt_button_text">{$btr->sviat__checkbox__order_receipt_create_return|escape}</span>
                                    <span class="sviat__checkbox_status_loader hidden">
                                        <span class="spinner"></span>
                                    </span>
                                </button>
                            </div>
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}