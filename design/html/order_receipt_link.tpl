{if $orderReceipt && !empty($orderReceipt->receipt_id)}
    <tr>
        <td>
            <span>Чек</span>
        </td>
        <td>
            <a href="https://check.checkbox.ua/{$orderReceipt->receipt_id|escape}" target="_blank">
                Переглянути чек
            </a>
        </td>
    </tr>
{/if}
