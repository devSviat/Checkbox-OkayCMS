<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Extenders;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Init\Init;

/** Хуки адмін-панелі: чеки замовлень, зміни, податки, способи оплати */
class BackendExtender implements ExtensionInterface
{
    private Design $design;
    private Request $request;
    private Settings $settings;
    private EntityFactory $entityFactory;
    private CheckboxHelper $checkboxHelper;

    public function __construct(
        Design $design,
        Request $request,
        Settings $settings,
        EntityFactory $entityFactory,
        CheckboxHelper $checkboxHelper
    ) {
        $this->design = $design;
        $this->request = $request;
        $this->settings = $settings;
        $this->entityFactory = $entityFactory;
        $this->checkboxHelper = $checkboxHelper;
    }

    /**
     * Хук головної сторінки адмінки: авторизує токен, визначає активну зміну,
     * передає в шаблон кількість порожніх чеків.
     */
    public function initializeFiscalReceipt(): void
    {
        // Раніше тут стояв getAccessToken(). Хук висить на evensCounters, тобто
        // виконується на КОЖНІЙ сторінці адмінки, і кожна з них чекала на
        // блокуючий HTTPS-логін у Checkbox — 480 мс із ~500 мс усієї сторінки.
        // Токен тут не потрібен: усі методи CheckboxShiftsHelper беруть його
        // самі й лише коли справді йдуть в API.
        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        $activeShift = $shiftsEntity->getActiveShift();
        if ($activeShift) {
            if ($activeShift->status === 'CREATED') {
                $this->checkboxHelper->checkShiftStatus($activeShift->shift_id);
                $activeShift = $shiftsEntity->getActiveShift();
            }
            $this->design->assign('checkboxActiveShift', $activeShift);
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $this->design->assign('emptyReceiptsCount', $receiptsEntity->count(['receipt_id' => '']));
    }

    /**
     * Хук сторінки замовлення: передає в шаблон чеки замовлення
     * і прапорець "не відправляти до Checkbox" для способу оплати.
     */
    public function initializeOrderReceipts($order, int $orderId): void
    {
        // BackendOrdersHelper::findOrder() віддає false для неіснуючого
        // замовлення — наприклад, коли менеджер відкриває картку за старим
        // посиланням. У PHP 8 звернення до властивості false дає Warning,
        // а той псує заголовки відповіді.
        if (empty($order)) {
            return;
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $this->design->assign('orderReceipts', $receiptsEntity->find(['order_id' => $order->id]));
        $this->design->assign('emptyOrderReceiptsCount', $receiptsEntity->count(['receipt_id' => '', 'order_id' => $order->id]));

        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $orderPaymentMethod = !empty($order->payment_method_id)
            ? $paymentsEntity->findOne(['id' => $order->payment_method_id])
            : null;

        $this->design->assign(
            'orderCheckboxDontSend',
            $orderPaymentMethod && !empty($orderPaymentMethod->{Init::PAYMENT_SKIP_FIELD})
        );
    }

    /** Хук картки товару: передає всі групи ПДВ і прив'язані до товару. */
    public function getProduct($product): void
    {
        $taxGroupsEntity = $this->entityFactory->get(TaxGroupsEntity::class);
        $this->design->assign('checkboxTaxes', $taxGroupsEntity->find());

        // На створенні товару $product - порожній stdClass без id. Звертання до
        // нього друкує попередження в тіло відповіді, після чого жоден заголовок
        // уже не виставити.
        $productId = (int)($product->id ?? 0);
        $this->design->assign(
            'checkboxProductTaxes',
            $productId > 0 ? $taxGroupsEntity->getProductTaxGroups($productId) : []
        );
    }

    /** Хук збереження товару: синхронізує прив'язку товару до груп ПДВ. */
    public function postProduct($product): void
    {
        $taxGroupsEntity = $this->entityFactory->get(TaxGroupsEntity::class);
        $productTaxes = $this->request->post('checkboxTaxes', 'array') ?? [];
        if (!is_array($productTaxes)) {
            $productTaxes = [];
        }

        $productId = (int)($product->id ?? 0);
        if ($productId <= 0) {
            return;
        }

        $taxGroupsEntity->deleteProductTaxGroups($productId);
        foreach ($productTaxes as $productTax) {
            $taxId = is_numeric($productTax) ? (int)$productTax : 0;
            if ($taxId > 0) {
                $taxGroupsEntity->addProductTaxGroup($productId, $taxId);
            }
        }
    }

    /** Хук списку замовлень: масово підвантажує останній чек для кожного замовлення. */
    public function findOrders(array $orders): void
    {
        if (empty($orders)) {
            return;
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $orderIds = array_map(fn($o) => (int)$o->id, $orders);
        $receipts = $receiptsEntity->find(['order_id' => $orderIds]);

        $receiptsMap = [];
        foreach ($receipts as $receipt) {
            $orderId = (int)$receipt->order_id;
            if (!isset($receiptsMap[$orderId]) || $receipt->id > $receiptsMap[$orderId]->id) {
                $receiptsMap[$orderId] = $receipt;
            }
        }

        foreach ($orders as $order) {
            $order->receipt = $receiptsMap[(int)$order->id] ?? null;
        }
    }

    /** Хук збереження способу оплати: зберігає Checkbox-поля типу, мітки і skip-прапорець. */
    public function postPayment($paymentMethod): void
    {
        $paymentMethod->{Init::PAYMENT_TYPE_FIELD} = $this->request->post(Init::PAYMENT_TYPE_FIELD);
        $paymentMethod->{Init::PAYMENT_LABEL_FIELD} = $this->request->post(Init::PAYMENT_LABEL_FIELD);
        $paymentMethod->{Init::PAYMENT_SKIP_FIELD} = $this->request->post(Init::PAYMENT_SKIP_FIELD) ? 1 : 0;
    }

    /** Хук зміни оплаченості замовлення: створює чеки для щойно оплачених замовлень. */
    public function orderMarkedPaid($output, array $ids, int $state): void
    {
        $this->checkboxHelper->createReceiptsForPaidOrders($ids, $state);
    }

    /**
     * Хук зміни статусу одного замовлення: відправляє чек якщо статус збігається
     * з налаштованим sviat__checkbox__order_status_id і ще немає чека продажу.
     */
    public function updateOrderStatus($resultUpdate, $order, int $orderStatusId): void
    {
        $statusId = $this->settings->get('sviat__checkbox__order_status_id');
        $checkboxStatusId = is_numeric($statusId) ? (int)$statusId : 0;
        if ($checkboxStatusId === 0 || $checkboxStatusId !== $orderStatusId) {
            return;
        }

        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        if (!$shiftsEntity->getActiveShift()) {
            return;
        }

        if (!empty($order->payment_method_id)) {
            $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
            $paymentMethod = $paymentsEntity->findOne(['id' => $order->payment_method_id]);
            if ($paymentMethod && !empty($paymentMethod->{Init::PAYMENT_SKIP_FIELD})) {
                return;
            }
        }

        // Перевіряємо існування саме чека продажу з receipt_id, а не "тип останнього чека":
        // після створення чека повернення останній чек має is_return=1 і колишня логіка
        // помилково запускала повторне виставлення чека продажу.
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $saleReceipts = $receiptsEntity->find(['order_id' => $order->id, 'is_return' => 0]);
        $hasSaleReceipt = false;
        foreach ($saleReceipts as $saleReceipt) {
            if (!empty($saleReceipt->receipt_id)) {
                $hasSaleReceipt = true;
                break;
            }
        }
        if (!$hasSaleReceipt) {
            $this->checkboxHelper->createReceipt((int)$order->id, false, null);
        }
    }

    /**
     * Хук масової зміни статусу замовлень: відправляє чеки для замовлень,
     * у яких встановлено потрібний статус і ще немає чека продажу.
     */
    public function changeStatus($output, array $ids): void
    {
        if (empty($ids)) {
            return;
        }

        $statusId = $this->settings->get('sviat__checkbox__order_status_id');
        $checkboxStatusId = is_numeric($statusId) ? (int)$statusId : 0;
        if ($checkboxStatusId === 0) {
            return;
        }

        $shiftsEntity = $this->entityFactory->get(CashierShiftsEntity::class);
        if (!$shiftsEntity->getActiveShift()) {
            return;
        }

        $ordersEntity = $this->entityFactory->get(OrdersEntity::class);
        $orders = $ordersEntity->mappedBy('id')->find(['id' => array_map('intval', $ids)]);
        if (empty($orders)) {
            return;
        }

        $ordersToProcess = array_filter($orders, fn($o) => (int)$o->status_id === $checkboxStatusId);
        if (empty($ordersToProcess)) {
            return;
        }

        $paymentsEntity = $this->entityFactory->get(PaymentsEntity::class);
        $paymentMethodIds = array_filter(array_map(fn($o) => (int)($o->payment_method_id ?? 0), $ordersToProcess));
        $paymentMethods = !empty($paymentMethodIds)
            ? $paymentsEntity->mappedBy('id')->find(['id' => array_unique($paymentMethodIds)])
            : [];

        // Перевіряємо існування саме чека продажу з receipt_id, а не "тип останнього чека":
        // після створення чека повернення останній чек має is_return=1 і колишня логіка
        // помилково запускала повторне виставлення чека продажу.
        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $receipts = $receiptsEntity->find(['order_id' => array_keys($ordersToProcess)]);
        $hasSaleReceiptMap = [];
        foreach ($receipts as $receipt) {
            if (empty($receipt->is_return) && !empty($receipt->receipt_id)) {
                $hasSaleReceiptMap[(int)$receipt->order_id] = true;
            }
        }

        foreach ($ordersToProcess as $order) {
            if (!empty($order->payment_method_id) && isset($paymentMethods[$order->payment_method_id])) {
                if (!empty($paymentMethods[$order->payment_method_id]->{Init::PAYMENT_SKIP_FIELD})) {
                    continue;
                }
            }
            if (empty($hasSaleReceiptMap[(int)$order->id])) {
                try {
                    $this->checkboxHelper->createReceipt((int)$order->id, false, null);
                } catch (\Exception $e) {
                    continue;
                }
            }
        }
    }
}
