<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;

/**
 * Фасад для роботи з Checkbox: агрегує API, зміни і чеки.
 * Перевіряє наявність залежних модулів і налаштувань перед делегуванням у хелпери.
 */
class CheckboxHelper
{
    private CheckboxApiHelper $apiHelper;
    private CheckboxShiftsHelper $shiftsHelper;
    private CheckboxReceiptsHelper $receiptsHelper;
    private CheckboxPrepaymentHelper $prepaymentHelper;
    private Settings $settings;
    private Modules $modules;

    public function __construct(
        CheckboxApiHelper $apiHelper,
        CheckboxShiftsHelper $shiftsHelper,
        CheckboxReceiptsHelper $receiptsHelper,
        CheckboxPrepaymentHelper $prepaymentHelper
    ) {
        $this->apiHelper = $apiHelper;
        $this->shiftsHelper = $shiftsHelper;
        $this->receiptsHelper = $receiptsHelper;
        $this->prepaymentHelper = $prepaymentHelper;

        $serviceLocator = ServiceLocator::getInstance();
        $this->settings = $serviceLocator->getService(Settings::class);
        $this->modules = $serviceLocator->getService(Modules::class);

        // Поділяємо один токен і стан помилок між усіма хелперами
        $this->shiftsHelper->syncWithHelper($this->apiHelper);
        $this->receiptsHelper->syncWithHelper($this->apiHelper);
        $this->prepaymentHelper->syncWithHelper($this->apiHelper);
    }

    /** @return array|false */
    public function getAccessToken()
    {
        return $this->apiHelper->getAccessToken();
    }

    /** @return array|false */
    public function createShift()
    {
        return $this->shiftsHelper->createShift();
    }

    /** @return array|false */
    public function checkShiftStatus(string $shiftId)
    {
        return $this->shiftsHelper->checkShiftStatus($shiftId);
    }

    public function cronCheckShifts(): void
    {
        $this->shiftsHelper->cronCheckShifts();
    }

    /** @return array|false */
    public function getShiftsList()
    {
        return $this->shiftsHelper->getShiftsList();
    }

    /** @return array|false */
    public function closeShift()
    {
        return $this->shiftsHelper->closeShift();
    }

    /** @return array|false */
    public function getCashierInfo()
    {
        return $this->shiftsHelper->getCashierInfo();
    }

    /**
     * Відкриває зміну якщо немає активної.
     * Викликати тільки коли є реальні операції — щоб не відкривати зміну даремно.
     */
    public function openShiftIfNeeded(): ?object
    {
        return $this->shiftsHelper->openShiftIfNeeded();
    }

    /** @return array|object|false */
    public function createReceipt(int $orderId, bool $isReturn = false, ?int $receiptId = null)
    {
        return $this->receiptsHelper->createReceipt($orderId, $isReturn, $receiptId);
    }

    /**
     * Cron-точка входу: перевіряє наявність модуля NovaPoshtaTracking і налаштування
     * sviat__checkbox__create_receipt_on_received перед запуском обробки.
     */
    public function processReceivedOrders()
    {
        if (!$this->modules->isActiveModule('Sviat', 'NovaPoshtaTracking')) {
            return false;
        }
        if (empty($this->settings->get('sviat__checkbox__create_receipt_on_received'))) {
            return false;
        }
        return $this->receiptsHelper->processReceivedOrders();
    }

    public function createReceiptsForPaidOrders(array $ids, int $status)
    {
        return $this->receiptsHelper->createReceiptsForPaidOrders($ids, $status);
    }

    /** @return array|object|null */
    public function createPrepaymentReceipt(int $orderId, int $advanceKopiyky, string $source)
    {
        return $this->receiptsHelper->createPrepaymentReceipt($orderId, $advanceKopiyky, $source);
    }

    /** @return array|object|null */
    public function createAfterPaymentReceipt(int $orderId, ?int $amountKopiyky, string $source, ?string $label = null)
    {
        return $this->receiptsHelper->createAfterPaymentReceipt($orderId, $amountKopiyky, $source, $label);
    }

    /** Джерело для кнопки післяплати рахує PHP, не шаблон. */
    public function orderPaymentSource(int $orderId): string
    {
        return $this->receiptsHelper->orderPaymentSource($orderId);
    }

    /** @return array|object|null */
    public function returnOrderChain(int $orderId)
    {
        return $this->receiptsHelper->returnOrderChain($orderId);
    }

    public function orderChainStatus(int $orderId): ?array
    {
        return $this->receiptsHelper->orderChainStatus($orderId);
    }

    /** Питає Checkbox про справжній стан ланцюжка. Єдиний шлях у мережу поза дією з чеком. */
    public function refreshOrderChainStatus(int $orderId): ?array
    {
        return $this->receiptsHelper->refreshOrderChainStatus($orderId);
    }

    /** @return array|object|null */
    public function fiscaliseOrder(int $orderId, ?int $emptyReceiptId = null)
    {
        return $this->receiptsHelper->fiscaliseOrder($orderId, $emptyReceiptId);
    }
}
