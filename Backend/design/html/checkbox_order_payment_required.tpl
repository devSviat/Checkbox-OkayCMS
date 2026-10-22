{if $settings->sviat__checkbox__require_payment_method}
    <div class="fn-sviat-checkbox-payment-required text_warning font_12"
         data-message="{$btr->sviat__checkbox__payment_required_error|escape}">
        {$btr->sviat__checkbox__payment_required_hint|escape}
    </div>
{/if}
