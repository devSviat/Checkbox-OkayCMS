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
                {if $smarty.get.return}
                    <a class="alert__button" href="{$smarty.get.return}">
                        {include file='svg_icon.tpl' svgId='return'}
                        <span>{$btr->general_back|escape}</span>
                    </a>
                {/if}
            </div>
        </div>
    </div>
{/if}

{*Інформаційний блок з інструкціями*}
<div class="row">
    <div class="col-lg-12 col-md-12 col-sm-12">
        <div class="alert alert--icon alert--info">
            <div class="alert__content">
                <div class="alert__title">{$btr->sviat__checkbox__instructions_title|escape}</div>
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
        </div>
    </div>
</div>

{*Головна форма сторінки*}
<form method="post" enctype="multipart/form-data">
    <input type="hidden" name="session_id" value="{$smarty.session.id}">

    {*Секція облікових даних касира*}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->sviat__checkbox__cashier_credentials|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;">
                            <i class="fa fn_icon_arrow fa-angle-down"></i>
                        </a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
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
                                       value="{$settings->sviat__checkbox__cashier_password|escape}"
                                       placeholder="{$btr->sviat__checkbox__password_placeholder|escape}" />
                            </div>
                        </div>
                        <div class="col-md-12">
                            <div class="heading_label">{$btr->sviat__checkbox__license_key|escape}</div>
                            <div class="mb-1">
                                <input name="sviat__checkbox__cashier_license_key"
                                       class="form-control"
                                       type="text"
                                       value="{$settings->sviat__checkbox__cashier_license_key|escape}"
                                       placeholder="{$btr->sviat__checkbox__license_key_placeholder|escape}" />
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {*Секція налаштувань чеків*}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->sviat__checkbox__receipt_settings|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;">
                            <i class="fa fn_icon_arrow fa-angle-down"></i>
                        </a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="heading_label">
                                {$btr->sviat__checkbox__receipt_text|escape}
                            </div>
                            <div class="mb-1">
                                <textarea name="sviat__checkbox__receipt_text"
                                          class="form-control okay_textarea"
                                          rows="3"
                                          placeholder="{$btr->sviat__checkbox__receipt_text_placeholder|escape}">{$settings->sviat__checkbox__receipt_text|escape}</textarea>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="heading_label">{$btr->sviat__checkbox__order_available_status|escape}</div>
                            <div class="mb-1">
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
                        <div class="col-md-6">
                            <div class="heading_label">{$btr->sviat__checkbox__message_how_send|escape}</div>
                            <div class="mb-1">
                                <select class="form-control selectpicker" name="sviat__checkbox__send_message">
                                    <option value="0" {if !$settings->sviat__checkbox__send_message}selected{/if}>
                                        {$btr->sviat__checkbox__message_not_send}
                                    </option>
                                    <option value="1" {if $settings->sviat__checkbox__send_message == 1}selected{/if}>
                                        {$btr->sviat__checkbox__message_email}
                                    </option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {*Секція автоматизації*}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed fn_toggle_wrap">
                <div class="heading_box">
                    {$btr->sviat__checkbox__automation_settings|escape}
                    <div class="toggle_arrow_wrap fn_toggle_card text-primary">
                        <a class="btn-minimize" href="javascript:;">
                            <i class="fa fn_icon_arrow fa-angle-down"></i>
                        </a>
                    </div>
                </div>
                <div class="toggle_body_wrap on fn_card">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="heading_label">
                                {$btr->sviat__checkbox__installed_at|escape}
                                <i class="fn_tooltips" title="{$btr->sviat__checkbox__installed_at_description|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            <div class="mb-1">
                                <input name="sviat__checkbox__installed_at"
                                       class="form-control"
                                       type="datetime-local"
                                       value="{$checkbox_installed_at|escape}" />
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="heading_label">
                                <span>{$btr->sviat__checkbox__create_receipt_on_received|escape}</span>
                                <i class="fn_tooltips" title="{$btr->sviat__checkbox__create_receipt_on_received_tooltip|escape}">
                                    {include file='svg_icon.tpl' svgId='icon_tooltips'}
                                </i>
                            </div>
                            <div class="mb-1">
                                {if !$is_nova_poshta_tracking_installed}
                                    <div class="alert alert--icon alert--info mb-1">
                                        <div class="alert__content">
                                            <div class="alert__title">
                                                {$btr->sviat__checkbox__create_receipt_on_received_info_title|escape}
                                            </div>
                                            <p>{$btr->sviat__checkbox__create_receipt_on_received_info_text|escape}</p>
                                        </div>
                                    </div>
                                {/if}
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
                                    <select class="form-control selectpicker"
                                            name="sviat__checkbox__create_receipt_on_received"
                                            disabled>
                                        <option value="0" selected>
                                            {$btr->sviat__checkbox__create_receipt_on_received_no}
                                        </option>
                                    </select>
                                {/if}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {*Блок з описом налаштування крона*}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--icon alert--warning">
                <div class="alert__content">
                    <div class="alert__title">{$btr->sviat__checkbox__cron_setup_title|escape}</div>
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

    {*Кнопка збереження*}
    <div class="row">
        <div class="col-lg-12 col-md-12">
            <div class="boxed">
                <div class="row">
                    <div class="col-lg-12 col-md-12">
                        <button type="submit" class="btn btn_small btn_blue float-md-right">
                            {include file='svg_icon.tpl' svgId='checked'}
                            <span>{$btr->general_apply|escape}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</form>

<script>
    sclipboard();
</script>