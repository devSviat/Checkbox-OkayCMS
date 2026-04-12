{$meta_title = $btr->sviat__left_checkbox_taxes scope=global}

<div class="row">
    <div class="col-lg-12 col-md-12">
        <div class="wrap_heading">
            <div class="box_heading heading_page">
                {$btr->sviat__left_checkbox_taxes|escape}
            </div>
            <div class="box_btn_heading">
                <a class="btn btn_small btn-info" href="{url controller='Sviat.Checkbox.TaxGroupAdmin'}">
                    {include file='svg_icon.tpl' svgId='plus'}
                    <span>{$btr->sviat__left_checkbox_taxes_add|escape}</span>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="boxed fn_toggle_wrap">
    {if $taxes}
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <form id="list_form" method="post" class="fn_form_list">
                    <input type="hidden" name="session_id" value="{$smarty.session.id}">

                    <div class="pages_wrap okay_list">
                        <div class="okay_list_head">
                            <div class="okay_list_heading okay_list_check">
                                <input class="hidden_check fn_check_all" type="checkbox" id="check_all_1" name="" value=""/>
                                <label class="okay_ckeckbox" for="check_all_1"></label>
                            </div>
                            <div class="okay_list_heading okay_list_name">{$btr->general_name|escape}</div>
                            <div class="okay_list_heading okay_list_status">{$btr->sviat__left_checkbox_taxes_code|escape}</div>
                            <div class="okay_list_heading okay_list_close"></div>
                        </div>

                        <div id="sortable" class="okay_list_body sortable">
                            {foreach $taxes as $tax}
                                <div class="fn_row okay_list_body_item">
                                    <div class="okay_list_row">

                                        <div class="okay_list_boding okay_list_check">
                                            <input class="hidden_check" type="checkbox" id="id_{$tax->id}" name="check[]" value="{$tax->id}"/>
                                            <label class="okay_ckeckbox" for="id_{$tax->id}"></label>
                                        </div>

                                        <div class="okay_list_boding okay_list_name">
                                            <a href="{url controller="Sviat.Checkbox.TaxGroupAdmin" id=$tax->id return=$smarty.server.REQUEST_URI}">
                                                {$tax->name|escape}
                                            </a>
                                        </div>

                                        <div class="okay_list_boding okay_list_status">
                                            {$tax->code|escape}
                                        </div>

                                        <div class="okay_list_boding okay_list_close">
                                            <button data-hint="{$btr->sviat__left_checkbox_taxes_delete|escape}" type="button" class="btn_close fn_remove hint-bottom-right-t-info-s-small-mobile  hint-anim" data-toggle="modal" data-target="#fn_action_modal" onclick="success_action($(this));">
                                                {include file='svg_icon.tpl' svgId='trash'}
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            {/foreach}
                        </div>

                        <div class="okay_list_footer fn_action_block">
                            <div class="okay_list_foot_left">
                                <div class="okay_list_boding okay_list_drag"></div>
                                <div class="okay_list_heading okay_list_check">
                                    <input class="hidden_check fn_check_all" type="checkbox" id="check_all_2" name="" value=""/>
                                    <label class="okay_ckeckbox" for="check_all_2"></label>
                                </div>
                                <div class="okay_list_option">
                                    <select name="action" class="selectpicker form-control">
                                        <option value="delete">{$btr->general_delete|escape}</option>
                                    </select>
                                </div>
                            </div>
                            <button type="submit" class="btn btn_small btn_blue">
                                {include file='svg_icon.tpl' svgId='checked'}
                                <span>{$btr->general_apply|escape}</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
            <div class="col-lg-12 col-md-12 col-sm 12 txt_center">
                {include file='pagination.tpl'}
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->sviat__left_checkbox_taxes_no|escape}</div>
        </div>
    {/if}
</div>
