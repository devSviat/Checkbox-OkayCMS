{* Лише для нового замовлення: вимога стосується створення, не редагування *}
{if $settings->sviat__checkbox__require_payment_on_create && empty($order->id)}
    <div class="fn-sviat-checkbox-payment-required text_warning font_12"
         data-message="{$btr->sviat__checkbox__payment_required_error|escape}">
        {$btr->sviat__checkbox__payment_required_hint|escape}
    </div>
{/if}
