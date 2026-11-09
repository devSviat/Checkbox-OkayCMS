<?php

/**
 * Заглушка сутності старого модуля Sviat/NovaPoshtaTracking.
 *
 * Підключається лише за відсутності оригіналу (прогін релізу кладе в рушій лише Checkbox).
 */

namespace Okay\Modules\Sviat\NovaPoshtaTracking\Entities;

use Okay\Core\Entity\Entity;

class NovaPoshtaTrackingEntity extends Entity
{
    protected static $fields = ['id', 'order_id', 'int_doc_number', 'status_code', 'updated_at'];
    protected static $table = 'sviat__novaposhta_tracking';
    protected static $tableAlias = 'npt';
}
