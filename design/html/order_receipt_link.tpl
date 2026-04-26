{if $orderReceipt && !empty($orderReceipt->receipt_id)}
    <tr>
        <td>
            <span data-language="sviat_checkbox_order_receipt_label">{$lang->sviat__checkbox__order_receipt_label|escape}</span>
        </td>
        <td>
            <a href="https://check.checkbox.ua/{$orderReceipt->receipt_id|escape:'url'}" target="_blank" rel="noopener">
                {$lang->sviat__checkbox__order_receipt_view_link|escape}
            </a>
        </td>
    </tr>
{/if}
