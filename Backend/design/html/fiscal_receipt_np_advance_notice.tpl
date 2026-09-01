{* Попередження у формі накладної: клієнт уже вніс аванс.

   Поле «Оголошена цінність» задає заразом і контроль оплати, і накладений
   платіж, а модуль Нової Пошти про аванс не знає. Без цього рядка менеджер
   відправив би посилку на повну суму, і клієнт заплатив би двічі.

   Текст короткий навмисне: менеджеру тут потрібне одне число — скільки взяти
   при отриманні. Пояснення механіки він читати не буде. *}
{if $checkboxChain && $checkboxChain.pre_payment_status == 'PARTIAL_PAID' && $checkboxChain.left_to_pay}
    <div class="sviat__checkbox_alert sviat__checkbox_alert--warning mb-h">
        <div class="sviat__checkbox_alert__title">
            {$btr->sviat__checkbox__np_advance_title|escape}
            {($checkboxChain.paid_sum/100)|string_format:"%.2f"} {$btr->sviat__checkbox__chain_uah|escape}
        </div>
        <div class="sviat__checkbox_alert__details">
            {$btr->sviat__checkbox__np_advance_next|escape}:
            <b>{($checkboxChain.left_to_pay/100)|string_format:"%.2f"} {$btr->sviat__checkbox__chain_uah|escape}</b>
        </div>
    </div>
{/if}
