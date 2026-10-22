{* Підпис з'являється лише після спроби зберегти без оплати (checkbox.js);
   до того обов'язковість позначає зірочка біля «Оплата» (checkbox.css). *}
{if $settings->sviat__checkbox__require_payment_method}
    <div class="fn-sviat-checkbox-payment-required sviat__checkbox_payment_required text_warning font_12 hidden"
         data-message="{$btr->sviat__checkbox__payment_required_error|escape}">
        {$btr->sviat__checkbox__payment_required_hint|escape}
    </div>
{/if}
