<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Чи можна створити замовлення в адмінці без способу оплати.
 *
 * Без способу оплати Checkbox не знає, якою формою фіскалізувати чек, тож
 * перемикач у налаштуваннях модуля таке створення забороняє. Редагування
 * наявного замовлення не зачіпається: вимога стосується лише створення.
 */
final class CheckboxOrderPaymentGuard
{
    /**
     * @param object $order замовлення з BackendOrdersRequest::postOrder()
     */
    public static function blocksCreation(bool $enabled, $order): bool
    {
        if (!$enabled || !empty($order->id)) {
            return false;
        }

        // Опція «Не вибраний» у формі має value="0".
        return empty($order->payment_method_id);
    }
}
