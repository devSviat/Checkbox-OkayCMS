<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;

class CashierShiftsAdmin extends IndexAdmin
{
    public function fetch(CheckboxHelper $checkboxHelper, CashierShiftsEntity $shiftsEntity)
    {
        // Оновлюємо статус CLOSING-змін перед відображенням
        foreach ($shiftsEntity->find(['status' => 'CLOSING']) as $closingShift) {
            $checkboxHelper->checkShiftStatus($closingShift->shift_id);
        }

        $filter = [];
        $filter['page'] = max(1, $this->request->get('page', 'integer'));
        $filter['limit'] = 20;

        $shiftsCount = $shiftsEntity->count($filter);
        if ($this->request->get('page') == 'all') {
            $filter['limit'] = $shiftsCount;
        }

        $pagesCount = $filter['limit'] > 0 ? ceil($shiftsCount / $filter['limit']) : 0;
        $filter['page'] = min($filter['page'], $pagesCount);

        $this->design->assign('shifts', $shiftsEntity->find($filter));
        $this->design->assign('pages_count', $pagesCount);
        $this->design->assign('current_page', $filter['page']);

        $this->response->setContent($this->design->fetch('checkbox_shifts.tpl'));
    }
}
