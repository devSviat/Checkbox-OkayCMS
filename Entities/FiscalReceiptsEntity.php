<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Entities;

use Okay\Core\Entity\Entity;

/**
 * Зберігає фіскальні чеки. Поле receipt_id — ID чека в Checkbox API.
 * Якщо receipt_id порожній — чек збережено локально, але ще не відправлено до API
 * (наприклад, зміна була закрита в момент оплати).
 */
class FiscalReceiptsEntity extends Entity
{
    protected static $fields = [
        'id',
        'order_id',
        'receipt_id',
        'related_receipt_id',
        'is_return',
        'sent',
        'created_at',
        'updated_at',
    ];

    protected static $defaultOrderFields = ['id'];

    protected static $table = '__sviat__checkbox__fiscal_receipts';
}
