<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Core\Managers;
use Okay\Modules\Sviat\Checkbox\Services\AdminIdentity;
use Okay\Entities\ManagersEntity;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;

/** AJAX-ендпоінти для управління зміною та створення чеків з адмінки */
class FiscalReceiptAjaxController extends AbstractController
{
    /** Те саме право, що й у бекендових контролерів модуля (див. Init). */
    private const PERMISSION = 'sviat__checkbox';

    public function createShift(
        AdminIdentity $adminIdentity,
        CheckboxHelper $checkboxHelper,
        Managers $managers,
        ManagersEntity $managersEntity
    ): void {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        $response = $checkboxHelper->createShift();
        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    public function closeShift(
        AdminIdentity $adminIdentity,
        CheckboxHelper $checkboxHelper,
        Managers $managers,
        ManagersEntity $managersEntity
    ): void {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        $response = $checkboxHelper->closeShift();
        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /**
     * Перевіряє статус зміни та повертає оновлений HTML-рядок таблиці для підміни на фронті.
     */
    public function updateShift(
        AdminIdentity $adminIdentity,
        CheckboxHelper $checkboxHelper,
        CashierShiftsEntity $shiftsEntity,
        Managers $managers,
        ManagersEntity $managersEntity
    ): void {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

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
    public function createReceipt(
        AdminIdentity $adminIdentity,
        CheckboxHelper $checkboxHelper,
        FiscalReceiptsEntity $receiptsEntity,
        Managers $managers,
        ManagersEntity $managersEntity
    ): void {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

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

    /**
     * Ці ендпоінти викликає адмінка, але маршрути оголошені з to_front, тобто
     * запит іде через вітрину й повз авторизацію backend/index.php. Без цієї
     * перевірки будь-хто міг відкрити чи закрити зміну і фіскалізувати чек на
     * довільне замовлення — операції, які йдуть у податкову.
     *
     * Звідки береться логін менеджера, вирішує AdminIdentity: рушії
     * зберігають бекендову сесію по-різному.
     */
    private function isAllowed(
        AdminIdentity $adminIdentity,
        Managers $managers,
        ManagersEntity $managersEntity
    ): bool
    {
        $adminLogin = $adminIdentity->login();
        if (empty($adminLogin)) {
            $this->response->setStatusCode(401);
            $this->response->setContent(json_encode(['message' => 'Unauthorized']), RESPONSE_JSON);
            return false;
        }

        $manager = $managersEntity->get($adminLogin);
        if (empty($manager) || !$managers->access(self::PERMISSION, $manager)) {
            $this->response->setStatusCode(403);
            $this->response->setContent(json_encode(['message' => 'Access denied']), RESPONSE_JSON);
            return false;
        }

        return true;
    }
}
