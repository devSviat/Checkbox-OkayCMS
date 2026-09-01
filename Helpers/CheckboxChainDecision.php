<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Який чек належить замовленню.
 *
 * Статус ланцюжка сюди передається тим, що віддав Checkbox — обчислювати його
 * локально не можна: і чек передплати, і чек післяплати повертаються з
 * однаковим type SELL, тож єдине джерело істини це GET по ланцюжку.
 */
final class CheckboxChainDecision
{
    const ACTION_SALE          = 'sale';
    const ACTION_AFTER_PAYMENT = 'after_payment';
    const ACTION_NONE          = 'none';

    /**
     * Ланцюжок у замовлення є, але Checkbox не сказав, у якому він стані.
     * Це не те саме, що «ланцюжка немає»: null тут означав би дозвіл виставити
     * повний чек продажу поверх уже сплаченого авансу.
     */
    const STATUS_UNKNOWN = 'unknown';

    /**
     * @param string|null $chainStatus pre_payment_status ланцюжка; null — лише
     *                                 коли ланцюжка немає за даними бази, а не
     *                                 коли запит статусу не вдався (STATUS_UNKNOWN)
     */
    public static function forOrder(?string $chainStatus, bool $hasSaleReceipt): string
    {
        if ($chainStatus === 'PARTIAL_PAID') {
            return self::ACTION_AFTER_PAYMENT;
        }

        // FULL_PAID, CANCELLED, PARTIAL_CANCELLED — ланцюжок відпрацював або
        // закритий поверненням; STATUS_UNKNOWN — стан невідомий. У всіх випадках
        // автоматика не втручається: помилка тут коштує зайвого чека.
        if ($chainStatus !== null) {
            return self::ACTION_NONE;
        }

        return $hasSaleReceipt ? self::ACTION_NONE : self::ACTION_SALE;
    }
}
