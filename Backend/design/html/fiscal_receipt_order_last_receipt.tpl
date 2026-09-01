{if $order->receipt}
    {* Аванс — єдиний стан, який треба назвати окремо: замовлення ще не
       фіскалізоване повністю. Закритий ланцюжок і звичайний продаж однаково
       означають «усе виставлено», тож підпис у них спільний і звичний. *}
    {if $order->receipt->receipt_type == 'prepayment'}
        {$sviatCheckboxLastReceiptLabel = $btr->sviat__checkbox__receipt_type_prepayment}
    {elseif $order->receipt->receipt_type == 'return' || $order->receipt->is_return}
        {$sviatCheckboxLastReceiptLabel = $btr->sviat__checkbox__receipt_type_return}
    {else}
        {$sviatCheckboxLastReceiptLabel = $btr->sviat__checkbox__orders_receipt_fiscalized}
    {/if}
    <a href="https://check.checkbox.ua/{$order->receipt->receipt_id|escape}"
        target="_blank"
        class="sviat__checkbox_receipt_badge mb-q hint-bottom-middle-t-info-s-small-mobile {if $order->receipt->receipt_type == 'return' || $order->receipt->is_return}sviat__checkbox_receipt_badge--return{else}sviat__checkbox_receipt_badge--sale{/if}"
        data-hint="{$sviatCheckboxLastReceiptLabel|escape} ({$order->receipt->created_at|date_format:"H:i d.m.Y"})">
        {$sviatCheckboxLastReceiptLabel|escape}
    </a>
{/if}