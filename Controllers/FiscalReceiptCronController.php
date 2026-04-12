<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;

/** Контролер для URL-тригерів cron (дублює scheduler, використовується при ручному запуску) */
class FiscalReceiptCronController extends AbstractController
{
    public function checkShifts(CheckboxHelper $checkboxHelper): void
    {
        $checkboxHelper->cronCheckShifts();
        exit;
    }

    public function checkEmptyReceipts(CheckboxHelper $checkboxHelper): void
    {
        $checkboxHelper->checkEmptyReceipts();
        exit;
    }

}
