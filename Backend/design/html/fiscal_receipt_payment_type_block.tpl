<div class="col-xs-12">
    <div class="boxed">
        <div class="row">
            <div class="col-lg-12 toggle_body_wrap on fn_card">
                <div class="row">
                    <div class="col-lg-4">
                        <div class="form-group">
                            <div class="heading_label" >{$btr->sviat__checkbox__type|escape}</div>
                            <select name="sviat__checkbox__payment_type" class="selectpicker form-control">
                                <option value="CASHLESS"{if $payment_method->sviat__checkbox__payment_type == 'CASHLESS'} selected{/if}>{$btr->sviat__checkbox__type_cashless|escape}</option>
                                <option value="CASH"{if $payment_method->sviat__checkbox__payment_type == 'CASH'} selected{/if}>{$btr->sviat__checkbox__type_cash|escape}</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <div class="heading_label" >{$btr->sviat__checkbox__label_name|escape}</div>
                            <input class="form-control" type="text" name="sviat__checkbox__payment_label" value="{$payment_method->sviat__checkbox__payment_label|escape}" list="payment_label_options">
                            <datalist id="payment_label_options">
                                <option value="Платіж NovaPay">
                                <option value="Платіж LiqPay">
                                <option value="Платіж RozetkaPay">
                                <option value="За реквізитами (IBAN)">
                                <option value="Готівка">
                                <option value="Інтернет еквайринг">
                                <option value="Платіж через інтегратора">
                            </datalist>
                        </div>
                    </div>
                    <div class="col-lg-4">
                        <div class="form-group">
                            <div class="heading_label" >{$btr->sviat__checkbox__dont_send|escape}</div>
                            <label class="switch switch-default">
                                <input class="switch-input" type="checkbox" name="sviat__checkbox__payment_skip" value="1"{if $payment_method->sviat__checkbox__payment_skip} checked{/if}>
                                <span class="switch-label"></span>
                                <span class="switch-handle"></span>
                            </label>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
