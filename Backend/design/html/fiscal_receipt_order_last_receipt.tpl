{if $order->receipt}
    <a href="https://check.checkbox.ua/{$order->receipt->receipt_id|escape}"
        target="_blank"
        class="sviat__checkbox_receipt_badge mb-q hint-bottom-middle-t-info-s-small-mobile {if $order->receipt->is_return}sviat__checkbox_receipt_badge--return{else}sviat__checkbox_receipt_badge--sale{/if}"
        data-hint="{if $order->receipt->is_return}{$btr->sviat__checkbox__orders_receipt_return|escape}{else}{$btr->sviat__checkbox__orders_receipt_pay|escape}{/if} ({$order->receipt->created_at|date_format:"H:i d.m.Y"})">
        {$btr->sviat__checkbox__orders_receipt_fiscalized|escape}
    </a>
{/if}