{$meta_title = $btr->sviat__checkbox__title scope=global}

{*Назва сторінки*}
<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="heading_page">{$btr->sviat__checkbox__title|escape}</div>
    </div>
</div>

{*Виведення успішних повідомлень*}
{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success == 'saved'}
                            {$btr->sviat__checkbox__settings_saved|escape}
                        {/if}
                    </div>
                </div>
                {if $checkboxReturnUrl}
                    <a class="alert__button" href="{$checkboxReturnUrl|escape}">
                        {include file='svg_icon.tpl' svgId='return'}
                        <span>{$btr->general_back|escape}</span>
                    </a>
                {/if}
            </div>
        </div>
    </div>
{/if}

{*Стан каси*}
<div class="boxed sviat__checkbox_state sviat__checkbox_state--{if !$checkboxCashierConfigured}error{elseif $checkboxActiveShift}ok{else}off{/if}">
    <span class="sviat__checkbox_state__icon">
        {if !$checkboxCashierConfigured}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 8v5"/><path d="M12 16.5v.01"/><path d="M10.3 4.3l-7.4 12.9a2 2 0 0 0 1.7 2.8h14.8a2 2 0 0 0 1.7 -2.8l-7.4 -12.9a2 2 0 0 0 -3.4 0z"/></svg>
        {elseif $checkboxActiveShift}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12l5 5l10 -10"/></svg>
        {else}
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 3"/></svg>
        {/if}
    </span>
    <div class="sviat__checkbox_state__text">
        {if !$checkboxCashierConfigured}
            <div class="sviat__checkbox_state__title">{$btr->sviat__checkbox__state_not_configured|escape}</div>
            <div>{$btr->sviat__checkbox__state_not_configured_hint|escape}</div>
        {elseif $checkboxActiveShift}
            <div class="sviat__checkbox_state__title">{$btr->sviat__checkbox__state_shift_open|escape}</div>
            <div>{$btr->sviat__checkbox__state_shift_open_hint|escape}</div>
        {else}
            <div class="sviat__checkbox_state__title">{$btr->sviat__checkbox__state_shift_closed|escape}</div>
            <div>{$btr->sviat__checkbox__state_shift_closed_hint|escape}</div>
        {/if}
    </div>
</div>

{*Головна форма сторінки*}
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="session_id" value="{$smarty.session.id}">
    {* Позначка «це саме наша форма»: чужий POST на цю адресу інакше стирає налаштування *}
    <input type="hidden" name="sviat__checkbox__settings_form" value="1">

    <div class="boxed tabs">
        <div class="heading_tabs">
            <div class="tab_navigation">
                <a href="#checkbox_cashier" class="heading_box tab_navigation_link">{$btr->sviat__checkbox__tab_cashier|escape}</a>
                <a href="#checkbox_triggers" class="heading_box tab_navigation_link">{$btr->sviat__checkbox__tab_triggers|escape}</a>
                <a href="#checkbox_receipt" class="heading_box tab_navigation_link">{$btr->sviat__checkbox__tab_receipt|escape}</a>
                <a href="#checkbox_advance" class="heading_box tab_navigation_link">{$btr->sviat__checkbox__tab_advance|escape}</a>
                <a href="#checkbox_help" class="heading_box tab_navigation_link">{$btr->sviat__checkbox__tab_help|escape}</a>
            </div>
        </div>

        <div class="tab_container">
            <div id="checkbox_cashier" class="tab">
                <div class="sviat__checkbox_narrow">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="heading_label">{$btr->sviat__checkbox__login|escape}</div>
                            <div class="mb-1">
                                <input name="sviat__checkbox__cashier_login"
                                       class="form-control"
                                       type="text"
                                       value="{$settings->sviat__checkbox__cashier_login|escape}"
                                       placeholder="{$btr->sviat__checkbox__login_placeholder|escape}" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="heading_label">{$btr->sviat__checkbox__password|escape}</div>
                            <div class="mb-1">
                                <input name="sviat__checkbox__cashier_password"
                                       class="form-control"
                                       type="password"
                                       value=""
                                       autocomplete="new-password"
                                       placeholder="{if $checkboxPasswordIsSet}{$btr->sviat__checkbox__secret_keep_placeholder|escape}{else}{$btr->sviat__checkbox__password_placeholder|escape}{/if}" />
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="heading_label">{$btr->sviat__checkbox__license_key|escape}</div>
                            <div class="mb-1">
                                <input name="sviat__checkbox__cashier_license_key"
                                       class="form-control"
                                       type="password"
                                       value=""
                                       autocomplete="new-password"
                                       placeholder="{if $checkboxLicenseIsSet}{$btr->sviat__checkbox__secret_keep_placeholder|escape}{else}{$btr->sviat__checkbox__license_key_placeholder|escape}{/if}" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div id="checkbox_triggers" class="tab">
                <p class="sviat__checkbox_triggers__intro">{$btr->sviat__checkbox__triggers_intro|escape}</p>

                <div class="sviat__checkbox_trigger">
                    <div class="sviat__checkbox_trigger__text">
                        <div class="sviat__checkbox_trigger__title">{$btr->sviat__checkbox__trigger_paid_title|escape}</div>
                        <div>{$btr->sviat__checkbox__trigger_paid_text|escape}</div>
                    </div>
                    <div class="sviat__checkbox_trigger__control">
                        <span class="sviat__checkbox_trigger__always">{$btr->sviat__checkbox__trigger_always|escape}</span>
                    </div>
                </div>

                <div class="sviat__checkbox_trigger">
                    <div class="sviat__checkbox_trigger__text">
                        <div class="sviat__checkbox_trigger__title">{$btr->sviat__checkbox__trigger_np_title|escape}</div>
                        <div>{if $is_nova_poshta_tracking_installed}{$btr->sviat__checkbox__trigger_np_text|escape}{else}{$btr->sviat__checkbox__create_receipt_on_received_info_text|escape}{/if}</div>
                    </div>
                    <div class="sviat__checkbox_trigger__control">
                        {if $is_nova_poshta_tracking_installed}
                            <select class="form-control selectpicker" name="sviat__checkbox__create_receipt_on_received">
                                <option value="0" {if !$settings->sviat__checkbox__create_receipt_on_received}selected{/if}>
                                    {$btr->sviat__checkbox__create_receipt_on_received_no}
                                </option>
                                <option value="1" {if $settings->sviat__checkbox__create_receipt_on_received == 1}selected{/if}>
                                    {$btr->sviat__checkbox__create_receipt_on_received_yes}
                                </option>
                            </select>
                        {else}
                            <span class="sviat__checkbox_trigger__always sviat__checkbox_trigger__always--off">{$btr->sviat__checkbox__trigger_module_required|escape}</span>
                        {/if}
                    </div>
                </div>

                <div class="sviat__checkbox_trigger">
                    <div class="sviat__checkbox_trigger__text">
                        <div class="sviat__checkbox_trigger__title">{$btr->sviat__checkbox__trigger_status_title|escape}</div>
                        <div>{$btr->sviat__checkbox__order_status_hint|escape}</div>
                    </div>
                    <div class="sviat__checkbox_trigger__control">
                    <select class="form-control selectpicker" name="sviat__checkbox__order_status_id">
                        <option value="0">{$btr->sviat__checkbox__no_status}</option>
                        {foreach $orders_statuses as $orders_status}
                            <option value="{$orders_status->id|escape}"
                                    {if $settings->sviat__checkbox__order_status_id == $orders_status->id}selected{/if}>
                                {$orders_status->name|escape}
                            </option>
                        {/foreach}
                    </select>
                    </div>
                </div>

                <div class="sviat__checkbox_no_receipt">
                    <div class="sviat__checkbox_trigger__title">{$btr->sviat__checkbox__no_receipt_title|escape}</div>
                    <ul>
                        <li>{$btr->sviat__checkbox__no_receipt_skip|escape}</li>
                        <li>{$btr->sviat__checkbox__no_receipt_exists|escape}</li>
                        <li>{$btr->sviat__checkbox__no_receipt_empty|escape}</li>
                    </ul>
                    <p class="mb-0">{$btr->sviat__checkbox__no_receipt_advance|escape}</p>
                </div>

                <div class="sviat__checkbox_section_title">{$btr->sviat__checkbox__additional_title|escape}</div>

                <div class="sviat__checkbox_trigger">
                    <div class="sviat__checkbox_trigger__text">
                        <div class="sviat__checkbox_trigger__title">{$btr->sviat__checkbox__require_payment_method|escape}</div>
                        <div>{$btr->sviat__checkbox__require_payment_method_tooltip|escape}</div>
                    </div>
                    <div class="sviat__checkbox_trigger__control">
                    <label class="switch switch-default">
                        <input class="switch-input" type="checkbox" name="sviat__checkbox__require_payment_method" value="1"{if $settings->sviat__checkbox__require_payment_method} checked{/if}>
                        <span class="switch-label"></span>
                        <span class="switch-handle"></span>
                    </label>
                    </div>
                </div>

                <div class="sviat__checkbox_trigger">
                    <div class="sviat__checkbox_trigger__text">
                        <div class="sviat__checkbox_trigger__title">{$btr->sviat__checkbox__installed_at|escape}</div>
                        <div>{$btr->sviat__checkbox__installed_at_description|escape}</div>
                    </div>
                    <div class="sviat__checkbox_trigger__control">
                    <input name="sviat__checkbox__installed_at"
                           class="form-control"
                           type="datetime-local"
                           value="{$checkbox_installed_at|escape}" />
                    </div>
                </div>
            </div>

            <div id="checkbox_receipt" class="tab">
                <div class="sviat__checkbox_narrow">
                    <div class="heading_label">
                        {$btr->sviat__checkbox__receipt_text|escape}
                    </div>
                    <div class="mb-1">
                        <textarea name="sviat__checkbox__receipt_text"
                                  class="form-control okay_textarea"
                                  rows="3"
                                  placeholder="{$btr->sviat__checkbox__receipt_text_placeholder|escape}">{$settings->sviat__checkbox__receipt_text|escape}</textarea>
                    </div>
                    <div class="heading_label">{$btr->sviat__checkbox__message_how_send|escape}</div>
                    <div class="mb-1">
                        <select class="form-control selectpicker fn-checkbox-delivery" name="sviat__checkbox__send_message">
                            <option value="0" {if $checkboxDeliveryMode == 0}selected{/if}>{$btr->sviat__checkbox__message_not_send|escape}</option>
                            <option value="1" {if $checkboxDeliveryMode == 1}selected{/if}>{$btr->sviat__checkbox__message_email|escape}</option>
                            <option value="2" {if $checkboxDeliveryMode == 2}selected{/if}>{$btr->sviat__checkbox__message_phone|escape}</option>
                            <option value="3" {if $checkboxDeliveryMode == 3}selected{/if}>{$btr->sviat__checkbox__message_email_and_phone|escape}</option>
                            <option value="4" {if $checkboxDeliveryMode == 4}selected{/if}>{$btr->sviat__checkbox__message_email_else_phone|escape}</option>
                        </select>
                    </div>
                    {* Ціна, Viber і баланс стосуються лише відправки на телефон. *}
                    <div class="fn-checkbox-phone-only{if !$checkboxPhoneMode} hidden{/if}">
                        <p class="sviat__checkbox_sources__intro">{$btr->sviat__checkbox__message_phone_hint|escape}</p>
                        {if $checkboxCashierConfigured}
                            <button type="submit" form="checkbox_sms_check" class="btn btn_small btn-outline-info mb-1">{$btr->sviat__checkbox__sms_check_button|escape}</button>
                        {/if}
                        {if $checkboxSmsCheckFailed}
                            <div class="alert alert--icon alert--error mb-1">
                                <div class="alert__content">
                                    <div class="alert__title">{$btr->sviat__checkbox__sms_check_failed|escape}</div>
                                </div>
                            </div>
                        {elseif $checkboxSmsWarning == 'off'}
                            <div class="alert alert--icon alert--error mb-1">
                                <div class="alert__content">
                                    <div class="alert__title">{$btr->sviat__checkbox__message_phone_off|escape}</div>
                                </div>
                            </div>
                        {elseif $checkboxSmsBalance}
                            <p class="sviat__checkbox_sources__intro">{$btr->sviat__checkbox__sms_balance|escape}: <strong>{$checkboxSmsBalance|escape} ₴</strong></p>
                            {if $checkboxSmsWarning == 'low'}
                                <div class="alert alert--icon alert--warning mb-1">
                                    <div class="alert__content">
                                        <div class="alert__title">{$btr->sviat__checkbox__sms_balance_low|escape}</div>
                                    </div>
                                </div>
                            {/if}
                        {/if}
                    </div>
                </div>
            </div>

            <div id="checkbox_advance" class="tab">
                <div class="sviat__checkbox_section_title sviat__checkbox_section_title--first">
                    {$btr->sviat__checkbox__chain_id_settings|escape}
                    <i class="fn_tooltips" title="{$btr->sviat__checkbox__chain_id_limits|escape}">
                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                    </i>
                </div>
                <p class="sviat__checkbox_sources__intro">{$btr->sviat__checkbox__chain_id_help|escape}</p>

                <div class="row">
                    <div class="col-md-5">
                        <div class="heading_label">{$btr->sviat__checkbox__chain_id_format|escape}</div>
                        <div class="mb-1">
                            <select class="form-control selectpicker fn-checkbox-chain-format" name="sviat__checkbox__chain_format">
                                <option value="prefix"{if $checkbox_chain_format != 'digits'} selected{/if}>
                                    {$btr->sviat__checkbox__chain_id_format_prefix|escape}
                                </option>
                                <option value="digits"{if $checkbox_chain_format == 'digits'} selected{/if}>
                                    {$btr->sviat__checkbox__chain_id_format_digits|escape}
                                </option>
                            </select>
                        </div>
                    </div>
                    <div class="col-md-4 fn-checkbox-chain-prefix-field{if $checkbox_chain_format == 'digits'} hidden{/if}">
                        <div class="heading_label">{$btr->sviat__checkbox__chain_id_prefix|escape}</div>
                        <div class="mb-1">
                            <input name="sviat__checkbox__chain_prefix" class="form-control fn-checkbox-chain-prefix"
                                   type="text" maxlength="32" value="{$checkbox_chain_prefix|escape}" />
                        </div>
                    </div>
                </div>

                {* Доповнення нулями неочевидне: «order-» і замовлення №7 дають
                   «order-0007», бо десять символів — межа Checkbox. Хай це
                   буде видно до збереження, а не в готовому чеку. *}
                <div class="sviat__checkbox_sources__preview">
                    <span class="sviat__checkbox_sources__preview_label">{$btr->sviat__checkbox__chain_id_preview|escape}:</span>
                    <span class="fn-checkbox-chain-preview"
                          data-digits="{$checkbox_chain_example_digits|escape}">{if $checkbox_chain_format == 'digits'}{$checkbox_chain_example_digits|escape}{else}{$checkbox_chain_example_prefix|escape}{/if}</span>
                </div>

                <div class="sviat__checkbox_section_title">
                    {$btr->sviat__checkbox__sources_settings|escape}
                    <i class="fn_tooltips" title="{$btr->sviat__checkbox__sources_help_scenarios|escape}">
                        {include file='svg_icon.tpl' svgId='icon_tooltips'}
                    </i>
                </div>
                <div class="sviat__checkbox_sources">
                    <p class="sviat__checkbox_sources__intro">
                        {$btr->sviat__checkbox__sources_help_text|escape}
                        <a href="https://wiki.checkbox.ua/uk/api/nakaz_601" target="_blank" rel="noopener noreferrer">
                            {$btr->sviat__checkbox__sources_help_link|escape}
                        </a>
                    </p>

                    {* Головне питання цієї сторінки — не «які галочки стоять»,
                       а «що побачить менеджер». Рядок відповідає на нього
                       одразу і оновлюється разом із перемикачами. *}
                    <div class="sviat__checkbox_sources__preview">
                        <span class="sviat__checkbox_sources__preview_label">{$btr->sviat__checkbox__sources_preview|escape}:</span>
                        <span class="fn-checkbox-sources-preview"></span>
                    </div>

                    <div class="sviat__checkbox_sources__list">
                        {foreach $checkbox_advance_sources as $checkboxSourceRow}
                            {if $checkboxSourceRow.editable}
                                {* Засіб платежу з місцем під назву — не один рядок, а список:
                                   NovaPay, LiqPay і WayForPay це три різні записи одного шаблону.
                                   Порожнє поле ігнорується, тож видалити запис — стерти назву. *}
                                <div class="sviat__checkbox_sources__group{if !$checkboxSourceRow.common} sviat__checkbox_sources__item--rare hidden{/if}">
                                    <div class="sviat__checkbox_sources__group_head">
                                        <span class="sviat__checkbox_sources__group_title">{$checkboxSourceRow.prefix|escape}…</span>
                                        <span class="sviat__checkbox_sources__form">{$btr->sviat__checkbox__sources_form_cashless|escape}</span>
                                    </div>
                                    <div class="fn-checkbox-source-names">
                                        {foreach $checkboxSourceRow.names as $checkboxSourceName}
                                            <div class="sviat__checkbox_sources__name_row" data-prefix="{$checkboxSourceRow.prefix|escape}">
                                                <label class="switch switch-default sviat__checkbox_sources__switch">
                                                    <input class="switch-input fn-checkbox-source-toggle" type="checkbox"
                                                           name="sviat__checkbox__source_on[{$checkboxSourceRow.key|escape}][{$checkboxSourceName@index}]"
                                                           value="1"{if $checkboxSourceName.on} checked{/if}>
                                                    <span class="switch-label"></span>
                                                    <span class="switch-handle"></span>
                                                </label>
                                                <input class="form-control fn-checkbox-source-name" type="text"
                                                       name="sviat__checkbox__source_name[{$checkboxSourceRow.key|escape}][{$checkboxSourceName@index}]"
                                                       value="{$checkboxSourceName.name|escape}"
                                                       placeholder="{$btr->sviat__checkbox__sources_name_placeholder|escape}">
                                                <button type="button" class="sviat__checkbox_sources__remove fn-checkbox-source-remove"
                                                        title="{$btr->sviat__checkbox__sources_remove_name|escape}">&times;</button>
                                            </div>
                                        {/foreach}
                                        {* Порожній рядок завжди напоготові: додати систему можна й без
                                           JS, а порожня назва просто ігнорується при збереженні.
                                           Перемикач вимкнений — увімкненим він обіцяв би те, чого ще
                                           немає; щойно назву введуть, JS його вмикає сам. *}
                                        <div class="sviat__checkbox_sources__name_row sviat__checkbox_sources__name_row--fresh" data-prefix="{$checkboxSourceRow.prefix|escape}">
                                            <label class="switch switch-default sviat__checkbox_sources__switch">
                                                <input class="switch-input fn-checkbox-source-toggle" type="checkbox"
                                                       name="sviat__checkbox__source_on[{$checkboxSourceRow.key|escape}][{$checkboxSourceRow.names|count}]"
                                                       value="1">
                                                <span class="switch-label"></span>
                                                <span class="switch-handle"></span>
                                            </label>
                                            <input class="form-control fn-checkbox-source-name" type="text"
                                                   name="sviat__checkbox__source_name[{$checkboxSourceRow.key|escape}][{$checkboxSourceRow.names|count}]"
                                                   value=""
                                                   placeholder="{$btr->sviat__checkbox__sources_name_placeholder|escape}">
                                            <button type="button" class="sviat__checkbox_sources__remove fn-checkbox-source-remove"
                                                    title="{$btr->sviat__checkbox__sources_remove_name|escape}">&times;</button>
                                        </div>
                                    </div>
                                    <button type="button" class="sviat__checkbox_sources__add fn-checkbox-source-add">
                                        {$btr->sviat__checkbox__sources_add_name|escape}
                                    </button>
                                </div>
                            {else}
                                <div class="sviat__checkbox_sources__row{if !$checkboxSourceRow.common} sviat__checkbox_sources__item--rare hidden{/if}"
                                     data-label="{$checkboxSourceRow.label|escape}">
                                    <label class="switch switch-default sviat__checkbox_sources__switch">
                                        <input class="switch-input fn-checkbox-source-toggle" type="checkbox"
                                               name="sviat__checkbox__source_enabled[{$checkboxSourceRow.key|escape}]"
                                               value="1"{if $checkboxSourceRow.enabled} checked{/if}>
                                        <span class="switch-label"></span>
                                        <span class="switch-handle"></span>
                                    </label>
                                    <div class="sviat__checkbox_sources__field">
                                        {* Мітку не правимо: її задає наказ № 601, і поле вводу тут
                                           читалось би як дозвіл переписати реквізит чека. *}
                                        <span class="sviat__checkbox_sources__fixed">{$checkboxSourceRow.label|escape}</span>
                                    </div>
                                    <span class="sviat__checkbox_sources__form{if $checkboxSourceRow.type == 'CASH'} sviat__checkbox_sources__form--cash{/if}">
                                        {if $checkboxSourceRow.type == 'CASH'}
                                            {$btr->sviat__checkbox__sources_form_cash|escape}
                                        {else}
                                            {$btr->sviat__checkbox__sources_form_cashless|escape}
                                        {/if}
                                    </span>
                                </div>
                            {/if}
                        {/foreach}
                    </div>

                    <button type="button" class="sviat__checkbox_sources__more fn-checkbox-sources-more"
                            data-more="{$btr->sviat__checkbox__sources_show_rare|escape}"
                            data-less="{$btr->sviat__checkbox__sources_hide_rare|escape}">
                        {$btr->sviat__checkbox__sources_show_rare|escape}
                    </button>
                </div>
            </div>

            <div id="checkbox_help" class="tab">
                <div class="sviat__checkbox_help">
                    <div>
                        <div class="sviat__checkbox_section_title sviat__checkbox_section_title--first">{$btr->sviat__checkbox__instructions_title|escape}</div>
                        <p><strong>{$btr->sviat__checkbox__instructions_registration_title|escape}</strong></p>
                        <p>{$btr->sviat__checkbox__instructions_registration_text|escape}</p>
                        <p>
                            <a href="https://wiki.checkbox.ua/uk/home" target="_blank" rel="noopener noreferrer">
                                {$btr->sviat__checkbox__instructions_registration_link|escape}
                            </a>
                        </p>
                        <p class="mt-2"><strong>{$btr->sviat__checkbox__instructions_credentials_title|escape}</strong></p>
                        <p>{$btr->sviat__checkbox__instructions_credentials_text|escape}</p>
                        <p class="mt-2"><strong>{$btr->sviat__checkbox__instructions_receipt_text_title|escape}</strong></p>
                        <p>{$btr->sviat__checkbox__receipt_text_order_id_description|escape}</p>
                        <ul class="mb-0 pl-1">
                            <li>
                                <a href="" class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile"
                                   data-hint="{$btr->sviat__checkbox__cron_setup_copy_hint|escape}"
                                   data-hint-copied="{$btr->sviat__checkbox__cron_setup_copy_copied|escape}">{literal}{$order_id}{/literal}</a> - {$btr->sviat__checkbox__receipt_text_order_id_label|escape}
                            </li>
                        </ul>
                    </div>
                    <div>
                        <div class="sviat__checkbox_section_title sviat__checkbox_section_title--first">{$btr->sviat__checkbox__cron_setup_title|escape}</div>
                        <p>{$btr->sviat__checkbox__cron_setup_text_1|escape}</p>
                        <p>
                            {$btr->sviat__checkbox__cron_setup_text_command|escape}
                            "<a href=""
                               class="fn_clipboard hint-bottom-middle-t-info-s-small-mobile"
                               data-hint="{$btr->sviat__checkbox__cron_setup_copy_hint|escape}"
                               data-hint-copied="{$btr->sviat__checkbox__cron_setup_copy_copied|escape}">
                                php {$config->root_dir}ok scheduler:run
                            </a>"
                            {$btr->sviat__checkbox__cron_setup_text_schedule|escape}
                        </p>
                        <p>{$btr->sviat__checkbox__cron_setup_text_2|escape}</p>
                        <p class="mt-2">
                            <a href="https://www.ukraine.com.ua/wiki/hosting/cron/add/"
                               target="_blank"
                               rel="noopener noreferrer">
                                {$btr->sviat__checkbox__cron_setup_docs_link|escape}
                            </a>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <div class="sviat__checkbox_actions">
            <button type="submit" class="btn btn_blue">
                {include file='svg_icon.tpl' svgId='checked'}
                <span>{$btr->general_apply|escape}</span>
            </button>
        </div>
    </div>
</form>

{* Окрема форма: кнопка всередині форми налаштувань зберегла б і їх. *}
<form id="checkbox_sms_check" method="post" action="index.php?controller=Sviat.Checkbox.CheckboxAdmin&amp;active_tab=checkbox_receipt">
    <input type="hidden" name="session_id" value="{$smarty.session.id}">
    <input type="hidden" name="sviat__checkbox__sms_check_form" value="1">
</form>

<script>
    sclipboard();
</script>
