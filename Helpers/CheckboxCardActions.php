<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Які дії пропонувати на картці замовлення.
 *
 * Правила мусять збігатися з тим, що дозволяє сервер: кнопка, яку сервер
 * відхилить, — це глухий кут, а прихована кнопка там, де дія законна, змушує
 * менеджера шукати обхід. Обидві помилки вже траплялися, тож рішення винесене
 * з екстендера сюди — поруч із перевірками, які його дублюють.
 *
 * @see CheckboxReceiptsHelper::createPrepaymentReceipt() — живий ланцюжок і чек продажу
 * @see CheckboxReceiptsHelper::createReceipt() — те саме для звичайного чека
 */
final class CheckboxCardActions
{
    /**
     * Стани, у яких ланцюжок уже нічого не тримає: гроші повернуто, і
     * замовлення повертається до звичайного шляху.
     */
    const CLOSED_STATUSES = ['CANCELLED', 'PARTIAL_CANCELLED'];

    /**
     * @param string|null $chainStatus стан ланцюжка або null, якщо його немає
     * @param bool $hasSaleReceipt чи фіскалізували замовлення колись
     * @param bool $hasUncoveredSale чи лишився чек продажу, який нічим не повернуто
     * @param bool $dontSend спосіб оплати позначено «не відправляти чек»
     * @param bool $orderPaid прапорець «Замовлення оплачено» — вся сума вже отримана
     * @return array{prepayment: bool, prepaymentHiddenByPaid: bool, afterPayment: bool, returnChain: bool, sale: bool, return: bool}
     */
    public static function forOrder(
        ?string $chainStatus,
        bool $hasSaleReceipt,
        bool $hasUncoveredSale,
        bool $dontSend,
        bool $orderPaid = false
    ): array {
        // Живий ланцюжок тримає замовлення: доки він відкритий, ні аванс, ні
        // повний чек продажу поверх нього неможливі. Скасований — не тримає.
        $chainIsLive = $chainStatus !== null && !in_array($chainStatus, self::CLOSED_STATUSES, true);

        return [
            // Аванс — це завжди сума, строго менша за товари: сервер саме так
            // і перевіряє (CheckboxPrepaymentRules::advanceIsValid). Якщо вся
            // сума вже отримана, «частини, що лишилась» просто не існує, і
            // кнопка веде в глухий кут — навіть коли ланцюжка ще не було.
            'prepayment' => !$chainIsLive && !$hasSaleReceipt && !$orderPaid,

            // Чому саме аванс сховано. Картка пояснює відсутність дії лише тоді,
            // коли її зняв прапорець оплати, — при чеку продажу чи живому
            // ланцюжку причину видно з самого списку чеків вище.
            'prepaymentHiddenByPaid' => !$chainIsLive && !$hasSaleReceipt && $orderPaid,

            'afterPayment' => $chainStatus === 'PARTIAL_PAID',
            'returnChain'  => in_array($chainStatus, ['PARTIAL_PAID', 'FULL_PAID'], true),

            // «Чи фіскалізували колись», а не «чи є що повертати»: другий чек
            // продажу сервер не випустить навіть після повернення першого.
            'sale' => !$dontSend && !$chainIsLive && !$hasSaleReceipt,

            // А тут навпаки — питання про те, чи лишилось що повертати. Інакше
            // кнопка повернення не зникає ніколи, і другий чек повернення на
            // той самий продаж робиться одним кліком.
            'return' => !$dontSend && !$chainIsLive && $hasUncoveredSale,
        ];
    }
}
