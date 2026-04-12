<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Entities;

use Okay\Core\Entity\Entity;

class CashierShiftsEntity extends Entity
{
    protected static $fields = [
        'id',
        'shift_id',
        'serial',
        'status',
        'shift_report_id',
        'opened_at',
        'closed_at',
    ];

    protected static $defaultOrderFields = ['opened_at DESC'];

    protected static $table = '__sviat__checkbox__cashier_shifts';

    /** Повертає активну зміну (статус CREATED або OPENED) або null */
    public function getActiveShift()
    {
        return $this->findOne(['opened' => 1]);
    }

    /** Фільтр: зміни зі статусом CREATED або OPENED */
    protected function filter__opened(): void
    {
        $this->select->where("(status='CREATED' OR status='OPENED')");
    }

    /** Фільтр: зміни зі статусом CLOSING або CLOSED */
    protected function filter__closed(): void
    {
        $this->select->where("(status='CLOSING' OR status='CLOSED')");
    }

    /** Фільтр: відкриті зміни, дата відкриття яких раніше ніж сьогодні */
    protected function filter__expired($value = true): void
    {
        if ($value) {
            $this->select->where("DATE(opened_at) < :current_date")
                ->bindValue('current_date', date('Y-m-d'));
        }
    }
}
