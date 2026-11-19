{* Блок «Чек Checkbox» на сторінці способу оплати. Перший рядок — поля самого способу оплати (імена ті самі, що
   до 1.8.0). Далі рядок на кожного перевізника з увімкненим модулем; рядок, чию післяплату вище не позначено,
   прихований і не надсилається: скрипт показує його, щойно вмикають перемикач перевізника. *}
<div class="col-xs-12">
    <div class="boxed sviat__checkbox_receipt fn_sviat_checkbox_receipt">
        <div class="heading_box">{$btr->sviat__checkbox__receipt_box_title|escape}</div>
        <p class="sviat__checkbox_receipt__intro">{$btr->sviat__checkbox__receipt_box_intro|escape}</p>
        <table class="sviat__checkbox_receipt__table">
            <thead>
                <tr>
                    <th scope="col">{$btr->sviat__checkbox__receipt_when|escape}</th>
                    <th scope="col">{$btr->sviat__checkbox__type|escape}</th>
                    <th scope="col">{$btr->sviat__checkbox__label_name|escape}</th>
                    <th scope="col">{$btr->sviat__checkbox__dont_send|escape}</th>
                </tr>
            </thead>
            <tbody>
                <tr class="sviat__checkbox_receipt__row sviat__checkbox_receipt__row--default">
                    <th scope="row" class="sviat__checkbox_receipt__when">
                        <span class="sviat__checkbox_receipt__logo" aria-hidden="true"></span>
                        <span>
                            <span class="sviat__checkbox_receipt__name">{$btr->sviat__checkbox__receipt_default|escape}</span>
                            <small class="sviat__checkbox_receipt__hint">{$btr->sviat__checkbox__receipt_default_hint|escape}</small>
                        </span>
                    </th>
                    <td data-label="{$btr->sviat__checkbox__type|escape}">
                        <select name="sviat__checkbox__payment_type" class="selectpicker form-control" aria-label="{$btr->sviat__checkbox__type|escape}">
                            <option value="CASHLESS"{if $payment_method->sviat__checkbox__payment_type == 'CASHLESS'} selected{/if}>{$btr->sviat__checkbox__type_cashless|escape}</option>
                            <option value="CASH"{if $payment_method->sviat__checkbox__payment_type == 'CASH'} selected{/if}>{$btr->sviat__checkbox__type_cash|escape}</option>
                        </select>
                    </td>
                    <td data-label="{$btr->sviat__checkbox__label_name|escape}">
                        <input class="form-control" type="text" name="sviat__checkbox__payment_label" value="{$payment_method->sviat__checkbox__payment_label|escape}" list="payment_label_options" aria-label="{$btr->sviat__checkbox__label_name|escape}">
                    </td>
                    <td data-label="{$btr->sviat__checkbox__dont_send|escape}">
                        <label class="switch switch-default">
                            <input class="switch-input" type="checkbox" name="sviat__checkbox__payment_skip" value="1" aria-label="{$btr->sviat__checkbox__dont_send|escape}"{if $payment_method->sviat__checkbox__payment_skip} checked{/if}>
                            <span class="switch-label"></span>
                            <span class="switch-handle"></span>
                        </label>
                    </td>
                </tr>
                {foreach $checkbox_carrier_rows as $row}
                    {$cb_field = 'sviat__checkbox__carrier_payments['|cat:$row.carrier|cat:']'}
                    <tr class="sviat__checkbox_receipt__row fn_sviat_checkbox_carrier{if !$row.on} hidden{/if}" data-switch="{$row.switch|escape}">
                        <th scope="row" class="sviat__checkbox_receipt__when">
                            <span class="sviat__checkbox_receipt__logo" aria-hidden="true">
                                {* viewBox обрізані по фарбі, тож висота 22 — це висота намальованого: логотипи однакові на око. *}
                                {if $row.carrier == 'novaposhta'}
                                    <svg data-carrier-logo="novaposhta" width="22" height="22" viewBox="1.41 1.41 21.18 21.18"><path fill="#f23c32" fill-rule="evenodd" d="M1.70703 11.293C1.31641 11.6835 1.31641 12.3167 1.70703 12.7072L5.99988 17.0001V14.0001V10.0001V7.00009L1.70703 11.293ZM6.99988 6.00009H9.99988V10.0001H13.9999V6.00009H16.9999L12.707 1.70718C12.3164 1.31668 11.6833 1.31668 11.2927 1.70718L6.99988 6.00009ZM17.9999 7.00009V10.0001V14.0001V17.0001L22.2927 12.7072C22.6833 12.3167 22.6833 11.6835 22.2927 11.293L17.9999 7.00009ZM16.9999 18.0001H13.9999V14.0001H9.99988V18.0001H6.99988L11.2927 22.293C11.6833 22.6835 12.3164 22.6835 12.707 22.293L16.9999 18.0001Z"/></svg>
                                {elseif $row.carrier == 'ukrposhta'}
                                    <svg data-carrier-logo="ukrposhta" width="23.24" height="22" viewBox="79 87 356 337"><path fill="#ffbe0c" d="M435 87C435 87 367.333 275.131 343.606 326.5C319.878 377.868 279.409 424 210.669 424C137.95 424 79 365.803 79 294.014C79 234.339 119.733 184.059 175.248 168.788L209.83 245.945C183.321 246.387 161.97 267.738 161.97 294.014C161.97 320.567 183.773 342.091 210.669 342.091C237.565 342.091 258.744 320.567 258.744 294.014C258.744 281.549 254.704 270.527 250.351 261.072C245.997 251.619 172.613 87 172.613 87H435Z"/></svg>
                                {elseif $row.carrier == 'rozetka'}
                                    <svg data-carrier-logo="rozetka" width="21.95" height="22" viewBox="0 0.74 42.16 42.26" fill="none"><path d="M42.1586 21.9643C42.1122 33.6352 32.6172 43.0464 20.9865 42.9998C9.35582 42.9532 -0.0462588 33.4488 0.000171225 21.7779C0.0466012 10.107 9.54154 0.695769 21.1723 0.74236C32.8262 0.78895 42.205 10.2934 42.1586 21.9643Z" fill="#221F1F"/><path d="M28.672 32.3309C26.4433 36.3377 23.6807 39.6223 20.5931 39.5525C18.6663 39.5059 16.8323 38.7837 15.1608 37.6422C15.6948 36.6638 19.061 31.7719 28.672 32.3309ZM41.6027 21.9646C41.5563 33.3093 32.3167 42.4876 20.9878 42.4178C9.65888 42.3712 0.558594 33.123 0.558594 21.7782C0.558594 14.4169 4.22657 9.12894 7.70882 6.28692C11.9804 2.81594 14.8823 5.47159 15.9037 7.49827C15.7876 6.63635 15.4394 4.16706 14.4876 2.39663C16.577 1.67448 18.8288 1.27846 21.1735 1.30175C32.5024 1.37164 41.6492 10.5965 41.6027 21.9646ZM19.9896 10.1306C22.9611 8.10395 28.0916 6.98578 30.8077 11.2255C29.1595 1.53471 22.0325 7.10226 19.9896 10.1306ZM15.8573 19.2856C16.4841 18.5868 17.2038 17.8879 18.0395 17.259C21.7539 14.3937 25.8165 13.6715 27.1398 15.6749C27.6505 16.4436 27.6737 17.5152 27.2791 18.68C28.6023 16.4902 29.0202 14.4635 28.2077 13.3454C26.838 11.4818 22.5896 12.763 18.7127 16.2107C17.5752 17.1658 16.6234 18.2374 15.8573 19.2856ZM9.3803 21.0095C11.2607 21.3589 13.3036 18.9595 13.9304 15.605C14.5572 12.2505 13.559 9.26871 11.6786 8.89599C9.79817 8.54656 7.75525 10.946 7.12844 14.3005C6.50164 17.6317 7.49988 20.6368 9.3803 21.0095ZM35.5204 16.9561C17.5288 29.0463 5.10874 22.9197 5.10874 22.9197C4.92302 27.1128 12.0965 39.9951 20.6396 40.1581C29.2059 40.3212 35.5204 16.9561 35.5204 16.9561ZM7.63917 14.3704C8.21955 11.2954 9.98389 9.05905 11.5857 9.38518C13.1876 9.68802 14.0233 12.4369 13.4661 15.5118C12.8858 18.5868 11.1214 20.8231 9.51959 20.497C7.89454 20.1942 7.0588 17.4453 7.63917 14.3704ZM11.0518 16.9794C11.7714 17.1425 12.5608 16.2573 12.8393 15.0226C13.1179 13.788 12.7465 12.6465 12.0268 12.5067C11.5393 12.3903 11.0286 12.763 10.6571 13.392C10.9821 13.555 11.1446 13.9743 10.9821 14.3238C10.8428 14.6499 10.5178 14.813 10.1928 14.7431C10.0535 15.838 10.4018 16.8163 11.0518 16.9794Z" fill="#05BC52"/></svg>
                                {/if}
                            </span>
                            <span>
                                <span class="sviat__checkbox_receipt__name">{$btr->getTranslation('sviat__checkbox__carrier_'|cat:$row.carrier)|escape}</span>
                                <small class="sviat__checkbox_receipt__hint">{$btr->getTranslation('sviat__checkbox__carrier_'|cat:$row.carrier|cat:'_hint')|escape}</small>
                            </span>
                        </th>
                        <td data-label="{$btr->sviat__checkbox__type|escape}">
                            <select name="{$cb_field|escape}[type]" class="selectpicker form-control" aria-label="{$btr->sviat__checkbox__type|escape}"{if !$row.on} disabled{/if}>
                                <option value="CASHLESS"{if $row.type == 'CASHLESS'} selected{/if}>{$btr->sviat__checkbox__type_cashless|escape}</option>
                                <option value="CASH"{if $row.type == 'CASH'} selected{/if}>{$btr->sviat__checkbox__type_cash|escape}</option>
                            </select>
                        </td>
                        <td data-label="{$btr->sviat__checkbox__label_name|escape}">
                            <input class="form-control" type="text" name="{$cb_field|escape}[label]" value="{$row.label|escape}" maxlength="255" list="payment_label_options" aria-label="{$btr->sviat__checkbox__label_name|escape}"{if !$row.on} disabled{/if}>
                        </td>
                        <td data-label="{$btr->sviat__checkbox__dont_send|escape}">
                            <label class="switch switch-default">
                                <input class="switch-input" type="checkbox" name="{$cb_field|escape}[skip]" value="1" aria-label="{$btr->sviat__checkbox__dont_send|escape}"{if $row.skip} checked{/if}{if !$row.on} disabled{/if}>
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </label>
                        </td>
                    </tr>
                {/foreach}
            </tbody>
        </table>
        <datalist id="payment_label_options">
            <option value="Платіж NovaPay">
            <option value="Платіж LiqPay">
            <option value="Платіж ROZETKAPAY">
            <option value="За реквізитами (IBAN)">
            <option value="Готівка">
            <option value="Інтернет еквайринг">
            <option value="Платіж через інтегратора">
        </datalist>
        {if $checkbox_carrier_rows}
            <p class="sviat__checkbox_receipt__note">{$btr->sviat__checkbox__receipt_box_note|escape}</p>
        {/if}
    </div>
</div>
