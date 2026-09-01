<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Controllers;

use Okay\Controllers\AbstractController;
use Okay\Core\BackendTranslations;
use Okay\Core\Managers;
use Okay\Modules\Sviat\Checkbox\Security\AdminIdentity;
use Okay\Modules\Sviat\Checkbox\Security\RequestOrigin;
use Okay\Entities\ManagersEntity;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentForm;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptPayloadBuilder;

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

        // `id` у відповіді хелпера — id рядка таблиці, а не чека в Checkbox.
        // Порожній `receipt_id` — заготовка при закритій зміні: чека ще немає,
        // і фронту його віддавати не можна.
        if (is_array($response) && !empty($response['receipt_id'])) {
            $receipt = $receiptsEntity->findOne(['id' => $response['id']]);
            if ($receipt) {
                $receiptData = [
                    'receipt_id'   => $receipt->receipt_id ?? '',
                    'is_return'    => !empty($receipt->is_return),
                    'receipt_type' => $receipt->receipt_type ?? 'sale',
                    'created_at'   => $receipt->created_at ?? '',
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
     * Чек авансу. Суму вводить менеджер, тож приймаємо гривні й переводимо в
     * копійки тим самим кодом, що й решта чеків — щоб копійка не загубилась.
     */
    public function createPrepaymentReceipt(
        AdminIdentity $adminIdentity,
        CheckboxHelper $checkboxHelper,
        BackendTranslations $translations,
        Managers $managers,
        ManagersEntity $managersEntity
    ): void {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        $orderId = $this->request->post('orderId', 'integer') ?: 0;
        $source  = (string)$this->request->post('source', 'string');

        // Сире значення, а не post(..., 'string'): фільтр рядка вирізає кому як
        // «зайвий символ», тож «500,50» ставало «50050» — чек на 50 тисяч
        // замість п'ятисот. Пробіл він лишає, і той обриває приведення до float:
        // «2 000,50» ставало 2 грн. Нормалізуємо й перевіряємо формат самі.
        $amount = self::parseAmount($this->request->post('amount'));
        if ($amount === null) {
            $this->response->setStatusCode(400);
            $this->response->setContent(
                json_encode(['message' => $translations->getTranslation('sviat__checkbox__errors_advance_not_a_number')], JSON_UNESCAPED_UNICODE),
                RESPONSE_JSON
            );
            return;
        }

        if (!CheckboxPaymentForm::isKnown($source)) {
            $this->response->setStatusCode(400);
            $this->response->setContent(json_encode(['message' => $translations->getTranslation('sviat__checkbox__errors_unknown_source')], JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
            return;
        }

        $response = $checkboxHelper->createPrepaymentReceipt(
            $orderId,
            CheckboxReceiptPayloadBuilder::toKopiyky($amount),
            $source
        );

        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /** Чек післяплати. Порожня сума означає «закрити борг повністю». */
    public function createAfterPaymentReceipt(
        AdminIdentity $adminIdentity,
        CheckboxHelper $checkboxHelper,
        BackendTranslations $translations,
        Managers $managers,
        ManagersEntity $managersEntity
    ): void {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        $orderId = $this->request->post('orderId', 'integer') ?: 0;
        $source  = (string)$this->request->post('source', 'string');
        $rawAmount = (string)$this->request->post('amount');

        if (!CheckboxPaymentForm::isKnown($source)) {
            $this->response->setStatusCode(400);
            $this->response->setContent(json_encode(['message' => $translations->getTranslation('sviat__checkbox__errors_unknown_source')], JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
            return;
        }

        $amount = null;
        if (trim($rawAmount) !== '') {
            $parsed = self::parseAmount($rawAmount);
            if ($parsed === null) {
                $this->response->setStatusCode(400);
                $this->response->setContent(
                    json_encode(['message' => $translations->getTranslation('sviat__checkbox__errors_advance_not_a_number')], JSON_UNESCAPED_UNICODE),
                    RESPONSE_JSON
                );
                return;
            }
            $amount = CheckboxReceiptPayloadBuilder::toKopiyky($parsed);
        }

        // Мітку не передаємо: тут менеджер обирає джерело сам, і стандартна
        // мітка з CheckboxPaymentForm лишається чинною (на відміну від
        // автоматичного шляху, де мітку несе спосіб оплати замовлення).
        $response = $checkboxHelper->createAfterPaymentReceipt($orderId, $amount, $source);
        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /**
     * Сума у гривнях із рядка, або null якщо це не сума.
     *
     * Приймає і крапку, і кому, і пробіли-роздільники тисяч — бо саме так
     * менеджер її й вводить. Усе інше відхиляє: мовчазне приведення до float
     * дає нуль або обрізане число, а це фіскальний документ на суму, якої
     * ніхто не вводив.
     */
    private static function parseAmount($raw): ?float
    {
        if (!is_scalar($raw)) {
            return null;
        }

        $value = str_replace([' ', "\xc2\xa0", ','], ['', '', '.'], trim((string)$raw));
        if ($value === '' || !preg_match('/^\d+(\.\d{1,2})?$/', $value)) {
            return null;
        }

        return (float)$value;
    }

    /**
     * Оновлює збережений стан ланцюжка з Checkbox.
     *
     * Стан лежить у нашій базі, щоб рендер картки не ходив у мережу. Розійтись
     * він може лише через дію ззовні — з кабінету Checkbox або іншої каси, — і
     * це єдиний спосіб її наздогнати: автоматичної звірки немає.
     */
    public function refreshChainStatus(
        AdminIdentity $adminIdentity,
        CheckboxHelper $checkboxHelper,
        Managers $managers,
        ManagersEntity $managersEntity
    ): void {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        $orderId = $this->request->post('orderId', 'integer') ?: 0;
        $checkboxHelper->refreshOrderChainStatus($orderId);

        $this->response->setContent(json_encode([], JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /** Повернення всього ланцюжка — окремими чеками повернення тут не обійтись. */
    public function returnChain(
        AdminIdentity $adminIdentity,
        CheckboxHelper $checkboxHelper,
        Managers $managers,
        ManagersEntity $managersEntity
    ): void {
        if (!$this->isAllowed($adminIdentity, $managers, $managersEntity)) {
            return;
        }

        $orderId = $this->request->post('orderId', 'integer') ?: 0;
        $response = $checkboxHelper->returnOrderChain($orderId);
        $this->response->setContent(json_encode($response, JSON_UNESCAPED_UNICODE), RESPONSE_JSON);
    }

    /**
     * Ці ендпоінти викликає адмінка, але маршрути оголошені з to_front, тобто
     * запит іде через вітрину й повз авторизацію backend/index.php. Без цієї
     * перевірки будь-хто міг відкрити чи закрити зміну і фіскалізувати чек на
     * довільне замовлення — операції, які йдуть у податкову.
     *
     * Перевіряються дві різні речі. *Хто* — це AdminIdentity: рушії зберігають
     * бекендову сесію по-різному. *Звідки* — RequestOrigin разом із вимогою
     * POST: кука адмінки має SameSite=Lax, тож міжсайтовий POST її не несе, а
     * top-level GET-навігація несе. Відкриття й закриття зміни параметрів не
     * мають узагалі, тож між голою адресою і податковою стоїть лише ця пара
     * перевірок.
     */
    private function isAllowed(
        AdminIdentity $adminIdentity,
        Managers $managers,
        ManagersEntity $managersEntity
    ): bool
    {
        if (!$this->request->method('post')) {
            $this->response->setStatusCode(405);
            $this->response->setContent(json_encode(['message' => 'Method Not Allowed']), RESPONSE_JSON);
            return false;
        }

        if (!RequestOrigin::isFromThisSite()) {
            $this->response->setStatusCode(403);
            $this->response->setContent(json_encode(['message' => 'Forbidden']), RESPONSE_JSON);
            return false;
        }

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
