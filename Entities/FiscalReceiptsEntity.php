<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Entities;

use Okay\Core\Entity\Entity;

/**
 * Зберігає фіскальні чеки. Поле receipt_id — ID чека в Checkbox API.
 * Якщо receipt_id порожній — чек збережено локально, але ще не відправлено до API
 * (наприклад, зміна була закрита в момент оплати).
 *
 * receipt_type — головна ознака; is_return лишається заповненим для сумісності зі
 * старими записами й вибірками. relation_id заповнений лише в чеків ланцюжка
 * передплати. is_test відрізняє чеки, пробиті на тестовій касі: хост у тесту і
 * бою однаковий, тож без цієї позначки їх не розрізнити.
 */
class FiscalReceiptsEntity extends Entity
{
    const TYPE_SALE          = 'sale';
    const TYPE_RETURN        = 'return';
    const TYPE_PREPAYMENT    = 'prepayment';
    const TYPE_AFTER_PAYMENT = 'after_payment';

    protected static $fields = [
        'id',
        'order_id',
        'receipt_id',
        'related_receipt_id',
        'relation_id',
        // Стан ланцюжка тримаємо в себе, щоб рендер картки замовлення не ходив
        // у Checkbox. Оновлюється в момент кожної нашої дії з ланцюжком; суми в
        // копійках, залишок рахується як total - paid.
        'chain_status',
        'chain_paid_sum',
        'chain_total_sum',
        'receipt_type',
        'is_return',
        'is_test',
        // Не пишеться й не читається кодом модуля — жодного разу за всю історію
        // репозиторію. 784 записи зі значеннями датовані 2024-09 — 2025-03,
        // тобто раніше, ніж модуль з'явився в git: їх лишила попередня
        // інтеграція. Лишено навмисно: це єдиний слід тих чеків, а nullable
        // datetime не коштує нічого.
        'sent',
        'created_at',
        'updated_at',
    ];

    protected static $defaultOrderFields = ['id'];

    protected static $table = '__sviat__checkbox__fiscal_receipts';
}
