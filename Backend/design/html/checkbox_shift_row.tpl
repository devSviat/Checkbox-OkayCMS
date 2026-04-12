<tr class="sviat__checkbox_receipts_tr">
    <td>
        {$shift->opened_at|date_format:"d.m.Y H:i:s"}
    </td>
    <td>
        {if $shift->closed_at}
            {$shift->closed_at|date_format:"d.m.Y H:i:s"}
        {else}
            <span class="text_grey">—</span>
        {/if}
    </td>
    <td>
        {assign var="status" value=$shift->status|upper}
        {if $status == 'CREATED'}
            <span class="sviat__checkbox_shift_status_badge sviat__checkbox_shift_status_badge--created">{$btr->sviat__checkbox__shift_status_created|escape}</span>
        {elseif $status == 'OPENING'}
            <span class="sviat__checkbox_shift_status_badge sviat__checkbox_shift_status_badge--opening">{$btr->sviat__checkbox__shift_status_opening|escape}</span>
        {elseif $status == 'OPENED'}
            <span class="sviat__checkbox_shift_status_badge sviat__checkbox_shift_status_badge--opened">{$btr->sviat__checkbox__shift_status_opened|escape}</span>
        {elseif $status == 'CLOSING'}
            <span class="sviat__checkbox_shift_status_badge sviat__checkbox_shift_status_badge--closing">{$btr->sviat__checkbox__shift_status_closing|escape}</span>
        {elseif $status == 'CLOSED'}
            <span class="sviat__checkbox_shift_status_badge sviat__checkbox_shift_status_badge--closed">{$btr->sviat__checkbox__shift_status_closed|escape}</span>
        {else}
            <span class="sviat__checkbox_shift_status_badge sviat__checkbox_shift_status_badge--created">{$shift->status|escape}</span>
        {/if}
    </td>
    <td class="sviat__checkbox_receipts_actions_cell">
        <div class="sviat__checkbox_actions_dropdown">
            <button type="button" class="sviat__checkbox_actions_btn" title="Дії">
                <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                    <path d="M4 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                    <path d="M11 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                    <path d="M18 12a1 1 0 1 0 2 0a1 1 0 1 0 -2 0"></path>
                </svg>
            </button>
            <ul class="sviat__checkbox_actions_menu">
                {if $shift->shift_report_id}
                    <li><a href="https://api.checkbox.ua/api/v1/reports/{$shift->shift_report_id|escape}/text" target="_blank">{$btr->sviat__checkbox__show_report|escape}</a></li>
                {/if}
                <li><a href="{url_generator route="Sviat_Checkbox_updateShift" absolute=1}" class="fn-sviat-checkbox-action-shift" data-id="{$shift->shift_id|escape}">{$btr->sviat__checkbox__refresh|escape}</a></li>
            </ul>
        </div>
    </td>
</tr>
