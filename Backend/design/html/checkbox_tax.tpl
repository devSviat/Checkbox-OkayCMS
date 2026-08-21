{if $tax->code}
    {$meta_title = $tax->name scope=global}
{else}
    {$meta_title = $btr->sviat__left_checkbox_taxes_add scope=global}
{/if}

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {if !$tax->code}
                    {$btr->sviat__left_checkbox_taxes_add|escape}
                {else}
                    {$tax->name|escape}
                {/if}
            </div>
        </div>
    </div>
</div>

{if $message_success}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--success">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_success == 'added'}
                            {$btr->sviat__left_checkbox_taxes_added|escape}
                        {elseif $message_success == 'updated'}
                            {$btr->sviat__left_checkbox_taxes_updated|escape}
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

{if $message_error}
    <div class="row">
        <div class="col-lg-12 col-md-12 col-sm-12">
            <div class="alert alert--center alert--icon alert--error">
                <div class="alert__content">
                    <div class="alert__title">
                        {if $message_error=='empty_code'}
                            {$btr->sviat__left_checkbox_taxes_errors_code|escape}
                        {elseif $message_error=='empty_name'}
                            {$btr->sviat__left_checkbox_taxes_errors_name|escape}
                        {elseif $message_error=='exists_code'}
                            {$btr->sviat__left_checkbox_taxes_errors_exists|escape}
                        {else}
                            {$message_error|escape}
                        {/if}
                    </div>
                </div>
            </div>
        </div>
    </div>
{/if}

<form method="post" enctype="multipart/form-data">
    <input type=hidden name="session_id" value="{$smarty.session.id}">
    <input type="hidden" name="lang_id" value="{$lang_id}" />
    <input type="hidden" name="id" value="{$tax->id}" />

    <div class="row">
        <div class="col-xs-12 ">
            <div class="boxed">
                <div class="row d_flex">
                    <div class="col-lg-9 col-md-6 col-sm-12">
                        <div class="heading_label">
                            {$btr->general_name|escape}
                        </div>
                        <div class="form-group">
                            <input class="form-control" name="name" type="text" value="{$tax->name|escape}" />
                        </div>
                    </div>
                    <div class="col-lg-3 col-md-6 col-sm-12">
                        <div class="heading_label">
                            {$btr->sviat__left_checkbox_taxes_code|escape}
                        </div>
                        <div class="form-group">
                            <input class="form-control" name="code" type="text" value="{$tax->code|escape}" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-lg-12 col-md-12 mt-1">
            <button type="submit" class="btn btn_small btn_blue float-md-right">
                {include file='svg_icon.tpl' svgId='checked'}
                <span>{$btr->general_apply|escape}</span>
            </button>
        </div>
    </div>
</form>