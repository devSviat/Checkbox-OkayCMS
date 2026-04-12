<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;

/** AJAX-ендпоінти для управління зміною та створення чеків з адмінки */
class FiscalReceiptAjaxController extends AbstractController
{
    public function createShift(CheckboxHelper $checkboxHelper): void
    {
        $response = $checkboxHelper->createShift();
        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    public function closeShift(CheckboxHelper $checkboxHelper): void
    {
        $response = $checkboxHelper->closeShift();
        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /**
     * Перевіряє статус зміни та повертає оновлений HTML-рядок таблиці для підміни на фронті.
     */
    public function updateShift(CheckboxHelper $checkboxHelper, CashierShiftsEntity $shiftsEntity): void
    {
        $shiftId = $this->request->post('id', 'string') ?? '';
        $response = $checkboxHelper->checkShiftStatus($shiftId);

        if (is_array($response) && !isset($response['message'])) {
            $shift = $shiftsEntity->findOne(['shift_id' => $shiftId]);
            $this->design->assign('shift', $shift);
            $this->design->setTemplatesDir('backend/design/html/');
            $this->design->setModuleTemplatesDir('Okay/Modules/Sviat/Checkbox/Backend/design/html/');
            $this->design->useModuleDir();
            $response['html'] = $this->design->fetch('checkbox_shift_row.tpl');
        }

        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /**
     * Створює чек і повертає його дані (receipt_id, дата, tax_url) для рендеру на фронті.
     */
    public function createReceipt(CheckboxHelper $checkboxHelper, FiscalReceiptsEntity $receiptsEntity): void
    {
        $orderId = $this->request->post('orderId', 'integer') ?: 0;
        $isReturn = (bool)$this->request->post('isReturn', 'integer');

        $response = $checkboxHelper->createReceipt($orderId, $isReturn);

        if (is_array($response) && !empty($response['id'])) {
            $receipt = $receiptsEntity->findOne(['receipt_id' => $response['id']]);
            if ($receipt) {
                $receiptData = [
                    'receipt_id' => $receipt->receipt_id ?? '',
                    'is_return'  => !empty($receipt->is_return),
                    'created_at' => $receipt->created_at ?? '',
                ];
                if (!empty($response['tax_url'])) {
                    $receiptData['tax_url'] = $response['tax_url'];
                }
                $response['receipt'] = $receiptData;
            }
        }

        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }
}
