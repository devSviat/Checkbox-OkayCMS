<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Core\Languages;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\ProductsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Entities\VariantsEntity;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;

/** Створення, збереження та обробка фіскальних чеків */
class CheckboxReceiptsHelper extends CheckboxApiHelper
{
    private CheckboxShiftsHelper $shiftsHelper;

    public function __construct(CheckboxShiftsHelper $shiftsHelper)
    {
        parent::__construct();
        $this->shiftsHelper = $shiftsHelper;
        $this->shiftsHelper->syncWithHelper($this);
    }

    /**
     * Прив'язує $accessToken і $errors за посиланням до іншого хелпера.
     * @see CheckboxShiftsHelper::syncWithHelper()
     */
    public function syncWithHelper(CheckboxApiHelper $helper): void
    {
        $this->accessToken = &$helper->accessToken;
        $this->errors = &$helper->errors;
    }

    /**
     * Відправляє чек продажу або повернення до Checkbox API і зберігає результат у БД.
     * Якщо зміна закрита — зберігає запис без receipt_id (порожній чек) для повторної обробки.
     * При $isReturn шукає останній чек продажу цього замовлення як related_receipt_id.
     *
     * @param int|null $receiptId ID запису в БД для оновлення (замість створення нового)
     * @return array|object|false
     */
    public function createReceipt(int $orderId, bool $isReturn = false, ?int $receiptId = null)
    {
        $this->clearErrors();

        if (empty($this->accessToken)) {
            $tokenResponse = $this->getAccessToken();
            if (!empty($this->errors)) {
                return $tokenResponse;
            }
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $order = $ordersEntity->get($orderId);
        if (!$order) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_find_order')];
        }

        $serviceLocator = ServiceLocator::getInstance();
        $ordersHelper = $serviceLocator->getService(OrdersHelper::class);
        $purchases = $ordersHelper->getOrderPurchasesList((int)$order->id);
        if (empty($purchases)) {
            return (object)['message' => $this->translations->getTranslation('sviat__checkbox__errors_find_purchases')];
        }

        // Зміна закрита — лишаємо запис без receipt_id; чек піде, коли зміну
        // відкриють і замовлення знову потрапить у createReceiptsForPaidOrders().
        //
        // Такий запис на замовлення має бути один. Викликів при закритій зміні
        // буває скільки завгодно — кнопка в адмінці, масова дія, хук оплати, —
        // і без пошуку наявного кожен додавав би ще один рядок.
        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        if ($shiftsEntity->count(['opened' => 1]) === 0) {
            $relatedReceiptId = $isReturn ? $this->findLastSaleReceiptId($orderId) : null;
            $placeholderId = $receiptId ?? $this->findEmptyReceiptId($orderId, $isReturn);

            return $this->saveReceiptToDatabase([], $orderId, $isReturn, $placeholderId, $relatedReceiptId);
        }

        $sendEmail = false;
        $validEmail = null;
        $sendMessageSetting = $this->settings->get('sviat__checkbox__send_message');
        $sendMessageSetting = is_numeric($sendMessageSetting) ? (int)$sendMessageSetting : 0;
        if ($sendMessageSetting
            && in_array($sendMessageSetting, [1, 3, 4], true)
            && is_string($order->email)
        ) {
            $normalizedEmail = trim($order->email);
            $validatedEmail = filter_var($normalizedEmail, FILTER_VALIDATE_EMAIL);
            if ($validatedEmail !== false) {
                $validEmail = $validatedEmail;
                $sendEmail = true;
            }
        }

        $cashier = $this->shiftsHelper->getCashierInfo();
        if (is_array($cashier) && isset($cashier['message'])) {
            return $cashier;
        }

        // Назви товарів беремо завжди на українській мові
        session_write_close();
        unset($_SESSION['lang_id'], $_SESSION['admin_lang_id']);
        $languagesService = ServiceLocator::getInstance()->getService(Languages::class);
        foreach ($languagesService->getAllLanguages() as $language) {
            if ($language->label == 'ua') {
                $languagesService->setLangId($language->id);
            }
        }

        $productsEntity = $this->entityFactory->get(ProductsEntity::class);
        $variantsEntity = $this->entityFactory->get(VariantsEntity::class);
        $taxGroupsEntity = $this->entityFactory->get(TaxGroupsEntity::class);

        foreach ($purchases as $purchase) {
            $purchase->taxes = $taxGroupsEntity->getProductTaxGroupCodes((int)$purchase->product_id);
            if ($product = $productsEntity->findOne(['id' => $purchase->product_id])) {
                $variant = $variantsEntity->findOne(['id' => $purchase->variant_id]);
                $purchase->fullProductName = $product->name . ($variant && $variant->name ? (' - ' . $variant->name) : '');
            }
        }

        // Розподіл знижки й переведення в копійки — у CheckboxReceiptPayloadBuilder,
        // щоб цю арифметику можна було перевірити тестами (див.
        // tests/Modules/Sviat/Checkbox/ReceiptPayloadBuilderTest.php).
        $undiscountedPrice = is_numeric($order->undiscounted_total_price) ? (float)$order->undiscounted_total_price : 0.0;
        $totalPrice = is_numeric($order->total_price) ? (float)$order->total_price : 0.0;

        $payload = CheckboxReceiptPayloadBuilder::build($purchases, $undiscountedPrice, $totalPrice, $isReturn);
        $goods = $payload['goods'];

        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $paymentMethod = $paymentsEntity->findOne(['id' => $order->payment_method_id]);

        if ($paymentMethod && !empty($paymentMethod->{Init::PAYMENT_SKIP_FIELD})) {
            return (object)['message' => 'Payment method is set to not send to Checkbox'];
        }

        $paymentType = $paymentMethod->{Init::PAYMENT_TYPE_FIELD} ?? 'CASH';
        $payments = [[
            'type' => is_string($paymentType) ? $paymentType : 'CASH',
            'value' => $payload['paymentValue']
        ]];

        $paymentLabel = $paymentMethod->{Init::PAYMENT_LABEL_FIELD} ?? null;
        if (!empty($paymentLabel) && is_string($paymentLabel)) {
            $payments[0]['label'] = $paymentLabel;
        }

        $cashierName = is_array($cashier) && isset($cashier['full_name']) && is_string($cashier['full_name'])
            ? $cashier['full_name']
            : '';

        $orderData = [
            'cashier_name' => $cashierName,
            'goods' => $goods,
            'payments' => $payments,
        ];
        if ($sendEmail && $validEmail !== null) {
            $orderData['delivery'] = ['email' => $validEmail];
        }

        $receiptText = $this->settings->sviat__checkbox__receipt_text ?? '';
        $receiptText = is_string($receiptText) ? $receiptText : '';
        if (!empty($receiptText)) {
            $orderIdStr = is_numeric($order->id) ? (string)$order->id : '0';
            $orderData['footer'] = str_replace('{$order_id}', $orderIdStr, $receiptText);
        }

        $relatedReceiptId = $isReturn ? $this->findLastSaleReceiptId($orderId) : null;

        $response = $this->makeApiRequest('receipts/sell', $orderData, ['method' => 'POST']);
        if (empty($this->errors)) {
            return $this->saveReceiptToDatabase($response, $orderId, $isReturn, $receiptId, $relatedReceiptId);
        }
        return $response;
    }

    /**
     * Cron-задача: знаходить ТТН зі статусом "Отримано" (код 9) і позначає замовлення як оплачені.
     * Обробляються тільки ТТН оновлені після sviat__checkbox__installed_at —
     * це захист від масового виставлення чеків при першому запуску модуля.
     *
     * Позначення замовлення як paid тригерить хук orderMarkedPaid → createReceiptsForPaidOrders,
     * який сам відкриє зміну, перевірить PAYMENT_SKIP та дублікати і створить чек.
     * Замовлення з PAYMENT_SKIP також позначаються як paid, але чек до Checkbox не надсилається.
     *
     * Перевірку активності модуля і налаштувань виконує CheckboxHelper::processReceivedOrders().
     */
    public function processReceivedOrders()
    {
        $trackingEntity = $this->entityFactory->get(NovaPoshtaTrackingEntity::class);
        $trackings = $trackingEntity->find(['status_code' => '9']);

        if (empty($trackings)) {
            return false;
        }

        $installedAt = $this->settings->get('sviat__checkbox__installed_at');
        if (!empty($installedAt)) {
            $installedTimestamp = strtotime($installedAt);
            if ($installedTimestamp !== false) {
                $trackings = array_filter($trackings, function ($tracking) use ($installedTimestamp) {
                    return !empty($tracking->updated_at) && strtotime($tracking->updated_at) >= $installedTimestamp;
                });
            }
        }

        if (empty($trackings)) {
            return false;
        }

        $orderIds = [];
        foreach ($trackings as $tracking) {
            if (!empty($tracking->order_id)) {
                $orderIds[] = (int)$tracking->order_id;
            }
        }
        $orderIds = array_unique($orderIds);

        if (empty($orderIds)) {
            return false;
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $purchasesEntity = $this->entityFactory->get(PurchasesEntity::class);
        $orders = $ordersEntity->mappedBy('id')->find(['id' => $orderIds]);

        // Відбираємо тільки замовлення що існують і мають товари
        $qualifyingIds = [];
        foreach ($orders as $order) {
            if ($purchasesEntity->count(['order_id' => $order->id]) > 0) {
                $qualifyingIds[] = (int)$order->id;
            }
        }

        if (empty($qualifyingIds)) {
            return false;
        }

        // Позначаємо замовлення як оплачені (paid=1)
        foreach ($qualifyingIds as $orderId) {
            $ordersEntity->update($orderId, (object)['paid' => 1]);
        }

        // Відразу формуємо чеки — не через хук, бо ми вже в контексті cron
        $this->createReceiptsForPaidOrders($qualifyingIds, 1);
    }

    /**
     * Відправляє чеки для щойно оплачених замовлень.
     * Пропускає замовлення без товарів, з PAYMENT_SKIP і з уже існуючим чеком продажу.
     * Відкриває зміну тільки якщо є хоча б одне замовлення для фіскалізації.
     *
     * @param array $ids    ID замовлень
     * @param int   $status 0 = оплата знята, решта = оплачено
     */
    public function createReceiptsForPaidOrders(array $ids, int $status)
    {
        if ($status === 0 || empty($ids)) {
            return false;
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $purchasesEntity = $this->entityFactory->get(PurchasesEntity::class);
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);

        $orders = $ordersEntity->mappedBy('id')->find(['id' => array_map('intval', $ids)]);

        $purchasesCounts = [];
        foreach ($orders as $order) {
            $purchasesCounts[$order->id] = $purchasesEntity->count(['order_id' => $order->id]);
        }

        $paymentMethodIds = [];
        foreach ($orders as $order) {
            if (!empty($order->payment_method_id)) {
                $paymentMethodIds[] = (int)$order->payment_method_id;
            }
        }
        $paymentMethods = [];
        if (!empty($paymentMethodIds)) {
            $paymentMethods = $paymentsEntity->mappedBy('id')->find(['id' => array_unique($paymentMethodIds)]);
        }

        // Перевіряємо існування фіскалізованого чека продажу, а не "тип останнього чека":
        // інакше після створення чека повернення останній чек буде з is_return=1
        // і система помилково повторно випустить новий чек продажу.
        $receipts = $receiptsEntity->find(['order_id' => array_keys($orders)]);
        $hasSaleReceiptMap = [];
        $emptyReceiptMap = [];
        foreach ($receipts as $receipt) {
            $orderId = (int)$receipt->order_id;
            if (empty($receipt->is_return) && !empty($receipt->receipt_id)) {
                $hasSaleReceiptMap[$orderId] = true;
            }
            // Порожній запис повернення не можна оновлювати під чек продажу — фіксуємо тільки порожні продажі
            if (empty($receipt->receipt_id) && empty($receipt->is_return)) {
                if (!isset($emptyReceiptMap[$orderId]) || $receipt->id > $emptyReceiptMap[$orderId]->id) {
                    $emptyReceiptMap[$orderId] = $receipt;
                }
            }
        }

        // orderId => ID порожнього запису в БД (null якщо немає) — для оновлення замість дублювання
        $ordersToReceipt = [];
        foreach ($orders as $order) {
            $orderId = (int)$order->id;
            if (empty($purchasesCounts[$orderId])) {
                continue;
            }
            if (!empty($order->payment_method_id) && isset($paymentMethods[$order->payment_method_id])) {
                if (!empty($paymentMethods[$order->payment_method_id]->{Init::PAYMENT_SKIP_FIELD})) {
                    continue;
                }
            }
            if (!empty($hasSaleReceiptMap[$orderId])) {
                continue; // вже є фіскалізований чек продажу
            }
            $emptyReceiptId = isset($emptyReceiptMap[$orderId]) ? (int)$emptyReceiptMap[$orderId]->id : null;
            $ordersToReceipt[$orderId] = $emptyReceiptId;
        }

        if (empty($ordersToReceipt)) {
            return false;
        }

        // Відкриваємо зміну тільки якщо є замовлення для фіскалізації
        $this->shiftsHelper->openShiftIfNeeded();

        foreach ($ordersToReceipt as $orderId => $emptyReceiptId) {
            $this->createReceipt($orderId, false, $emptyReceiptId);
        }
    }

    /**
     * Зберігає або оновлює запис чека в БД.
     * Якщо $response порожній — зберігає запис без receipt_id (чек відправлений не був).
     *
     * @param int|null    $receiptId       ID запису для оновлення (якщо null — шукає за receipt_id або створює новий)
     * @param string|null $relatedReceiptId receipt_id оригінального чека продажу (для повернення)
     */
    private function saveReceiptToDatabase(array $response, int $orderId, bool $isReturn = false, ?int $receiptId = null, ?string $relatedReceiptId = null): ?array
    {
        $receipt = new \stdClass();
        $receipt->order_id = $orderId;
        $receipt->is_return = (int)$isReturn;

        if (!empty($response['id'])) {
            $receipt->receipt_id = (string)$response['id'];

            $createdAt = strtotime($response['created_at'] ?? '');
            $receipt->created_at = $createdAt !== false ? date('Y-m-d H:i:s', $createdAt) : null;

            $updatedAt = strtotime($response['updated_at'] ?? '');
            $receipt->updated_at = $updatedAt !== false ? date('Y-m-d H:i:s', $updatedAt) : null;

            if ($isReturn && !empty($relatedReceiptId)) {
                $receipt->related_receipt_id = $relatedReceiptId;
            }
        }

        $filter = [];
        if ($receiptId !== null) {
            $filter['id'] = $receiptId;
        } elseif (isset($receipt->receipt_id)) {
            $filter['receipt_id'] = $receipt->receipt_id;
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $existingReceipt = !empty($filter) ? $receiptsEntity->findOne($filter) : null;

        if ($existingReceipt) {
            $receiptsEntity->update($existingReceipt->id, $receipt);
            $receiptId = $existingReceipt->id;
        } else {
            $receiptId = $receiptsEntity->add($receipt);
        }

        if ($receiptId) {
            $savedReceipt = $receiptsEntity->findOne(['id' => $receiptId]);
            return $savedReceipt ? (array)$savedReceipt : null;
        }

        return null;
    }

    /**
     * Запис без receipt_id, який уже чекає на відправку для цього замовлення.
     *
     * Продаж і повернення розділені: порожній запис повернення не можна
     * віддавати під чек продажу.
     */
    private function findEmptyReceiptId(int $orderId, bool $isReturn): ?int
    {
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $existing = $receiptsEntity->findOne([
            'order_id'   => $orderId,
            'is_return'  => (int)$isReturn,
            'receipt_id' => '',
        ]);

        return $existing ? (int)$existing->id : null;
    }

    /** Повертає receipt_id останнього чека продажу для замовлення (використовується у чеках повернення) */
    private function findLastSaleReceiptId(int $orderId): ?string
    {
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $lastSaleReceipt = $receiptsEntity->order('id_desc')->findOne(['order_id' => $orderId, 'is_return' => 0]);
        return ($lastSaleReceipt && !empty($lastSaleReceipt->receipt_id)) ? $lastSaleReceipt->receipt_id : null;
    }
}
