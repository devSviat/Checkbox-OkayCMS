<div class="fn_step-6">
    <div class="heading_label">
        {$btr->sviat__left_checkbox_taxes|escape}
        <i class="fn_tooltips" title="{$btr->sviat__left_checkbox_taxes_product_tooltip|escape}">
            {include file='svg_icon.tpl' svgId='icon_tooltips'}
        </i>
    </div>
    <div class="">
        <select name="checkboxTaxes[]" class="selectpicker form-control mb-1{if !$checkboxTaxes} hidden{/if}" data-selected-text-format="count" multiple size="5">
            {foreach $checkboxTaxes as $checkboxTax}
                <option value="{$checkboxTax->id}" {if $checkboxTax->id|in_array:$checkboxProductTaxes || !$product->id}selected=""{/if}>{$checkboxTax->name|escape}</option>
            {/foreach}
        </select>
    </div>
</div>
