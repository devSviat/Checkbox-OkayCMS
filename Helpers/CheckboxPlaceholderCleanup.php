<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;

/** Які заготовки чеків зайві: на замовлення потрібна лише одна. */
final class CheckboxPlaceholderCleanup
{
    /**
     * @param iterable<object> $rows рядки чеків з id, order_id, receipt_type, receipt_id
     * @return int[] id усіх заготовок, крім найновішої в парі order_id + receipt_type
     */
    public static function surplusIds(iterable $rows): array
    {
        $newest = [];
        $surplus = [];
        foreach ($rows as $row) {
            // Умова запиту тут не гарантія: зайвий рядок у вибірці не сміє
            // обернутись видаленим фіскальним чеком.
            if (!empty($row->receipt_id)) {
                continue;
            }
            $type = $row->receipt_type ?? FiscalReceiptsEntity::TYPE_SALE;
            if ($type !== FiscalReceiptsEntity::TYPE_SALE && $type !== FiscalReceiptsEntity::TYPE_RETURN) {
                continue;
            }

            $key = $row->order_id . ':' . $type;
            $id = (int)$row->id;
            if (!isset($newest[$key])) {
                $newest[$key] = $id;
                continue;
            }
            $surplus[] = min($id, $newest[$key]);
            $newest[$key] = max($id, $newest[$key]);
        }

        return $surplus;
    }
}
