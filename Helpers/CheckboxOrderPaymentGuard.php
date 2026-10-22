<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Чи можна зберегти замовлення в адмінці без способу оплати.
 *
 * Без способу оплати Checkbox не знає, якою формою фіскалізувати чек, тож
 * перемикач у налаштуваннях модуля забороняє зберігати таке замовлення — і
 * нове, і наявне.
 */
final class CheckboxOrderPaymentGuard
{
    /**
     * @param object $order замовлення з BackendOrdersRequest::postOrder()
     */
    public static function blocksSave(bool $enabled, $order): bool
    {
        if (!$enabled) {
            return false;
        }

        // Опція «Не вибраний» у формі має value="0".
        return empty($order->payment_method_id);
    }
}
