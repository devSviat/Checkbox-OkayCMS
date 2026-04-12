<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrderStatusEntity;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;

class CheckboxAdmin extends IndexAdmin
{
    public function fetch(
        CheckboxHelper $checkboxHelper,
        CashierShiftsEntity $shiftsEntity,
        OrderStatusEntity $orderStatusEntity
    ) {
        if ($this->request->method('post')) {
            $this->settings->set('sviat__checkbox__receipt_text', $this->request->post('sviat__checkbox__receipt_text'));
            $this->settings->set('sviat__checkbox__order_status_id', $this->request->post('sviat__checkbox__order_status_id', 'integer'));
            $this->settings->set('sviat__checkbox__send_message', $this->request->post('sviat__checkbox__send_message', 'integer'));
            $this->settings->set('sviat__checkbox__cashier_login', $this->request->post('sviat__checkbox__cashier_login'));
            $this->settings->set('sviat__checkbox__cashier_password', $this->request->post('sviat__checkbox__cashier_password'));
            $this->settings->set('sviat__checkbox__cashier_license_key', $this->request->post('sviat__checkbox__cashier_license_key'));

            $modules = ServiceLocator::getInstance()->getService(Modules::class);
            if ($modules->isActiveModule('Sviat', 'NovaPoshtaTracking')) {
                $this->settings->set('sviat__checkbox__create_receipt_on_received', $this->request->post('sviat__checkbox__create_receipt_on_received', 'integer'));
            } else {
                // Якщо модуль НП вимкнено — скидаємо налаштування, щоб cron не запускався
                $this->settings->set('sviat__checkbox__create_receipt_on_received', 0);
            }

            $installedAt = $this->request->post('sviat__checkbox__installed_at');
            if (!empty($installedAt)) {
                $timestamp = strtotime($installedAt);
                if ($timestamp !== false) {
                    $this->settings->set('sviat__checkbox__installed_at', date('Y-m-d H:i:s', $timestamp));
                }
            }

            $this->postRedirectGet->storeMessageSuccess('saved');
            $this->postRedirectGet->redirect();
        }

        // Оновлюємо статус CLOSING-змін перед відображенням
        foreach ($shiftsEntity->find(['status' => 'CLOSING']) as $closingShift) {
            $checkboxHelper->checkShiftStatus($closingShift->shift_id);
        }

        $modules = ServiceLocator::getInstance()->getService(Modules::class);

        $installedAtRaw = $this->settings->get('sviat__checkbox__installed_at');
        $installedAtInput = !empty($installedAtRaw)
            ? date('Y-m-d\TH:i', strtotime($installedAtRaw))
            : '';

        $this->design->assign('orders_statuses', $orderStatusEntity->find());
        $this->design->assign('is_nova_poshta_tracking_installed', $modules->isActiveModule('Sviat', 'NovaPoshtaTracking'));
        $this->design->assign('checkbox_installed_at', $installedAtInput);

        $this->response->setContent($this->design->fetch('checkbox_admin.tpl'));
    }
}
