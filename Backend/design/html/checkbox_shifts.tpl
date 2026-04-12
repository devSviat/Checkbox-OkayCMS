{$meta_title = $btr->sviat__checkbox__shifts scope=global}

<div class="main_header">
    <div class="main_header__item">
        <div class="main_header__inner">
            <div class="box_heading heading_page">
                {$btr->sviat__checkbox__shifts|escape}
            </div>
        </div>
    </div>
</div>

<div class="boxed fn_toggle_wrap">
    {if $shifts}
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm-12">
                <div class="sviat__checkbox_table_wrapper">
                    <table class="sviat__checkbox_receipts_table">
                        <thead>
                            <tr>
                                <th>{$btr->sviat__checkbox__shift_opened_at|escape}</th>
                                <th>{$btr->sviat__checkbox__shift_closed_at|escape}</th>
                                <th>{$btr->sviat__checkbox__shift_status|escape}</th>
                                <th style="width: 100px; min-width: 100px;">{$btr->general_activities|escape}</th>
                            </tr>
                        </thead>
                        <tbody>
                            {foreach $shifts as $shift}
                                {include "checkbox_shift_row.tpl"}
                            {/foreach}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12 col-md-12 col-sm 12 txt_center">
                {include file='pagination.tpl'}
            </div>
        </div>
    {else}
        <div class="heading_box mt-1">
            <div class="text_grey">{$btr->sviat__checkbox__shifts_no|escape}</div>
        </div>
    {/if}
</div>