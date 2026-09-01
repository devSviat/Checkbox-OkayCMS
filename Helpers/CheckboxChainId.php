<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Ідентифікатор ланцюжка передплати.
 *
 * Checkbox вимагає унікальності в межах організації і довжини від 10 символів,
 * тож голий номер замовлення не підходить — звідси префікс. Унікальність дає
 * безкоштовний захист від подвійного натискання: повторний виклик з тим самим
 * значенням Checkbox відхиляє замість створення другого ланцюжка.
 *
 * Префікс навмисно нейтральний: модуль ставлять різні магазини, а значення
 * видно в їхніх кабінетах Checkbox. Унікальність від цього не страждає — вона
 * потрібна лише в межах однієї організації, а Checkbox-акаунт у кожного свій.
 */
final class CheckboxChainId
{
    const PREFIX = 'prepayment-';

    /**
     * @param int $existingChains скільки ланцюжків це замовлення вже мало
     *                            (повернутий ланцюжок не звільняє свій ідентифікатор)
     */
    public static function build(int $orderId, int $existingChains = 0): string
    {
        $id = self::PREFIX . $orderId;
        if ($existingChains > 0) {
            $id .= '-' . ($existingChains + 1);
        }

        return $id;
    }
}
