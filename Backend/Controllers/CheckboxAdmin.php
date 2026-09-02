<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrderStatusEntity;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentCatalogue;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentSources;
use Okay\Modules\Sviat\Checkbox\Init\Init;

class CheckboxAdmin extends IndexAdmin
{
    public function fetch(
        CheckboxHelper $checkboxHelper,
        CashierShiftsEntity $shiftsEntity,
        OrderStatusEntity $orderStatusEntity
    ) {
        // Не просто «був POST», а «прийшла саме ця форма». На сторінці є й чужі
        // форми з action="" — вихід із адмінки, наприклад, — і будь-яка з них
        // доходить сюди порожньою. Форма володіє всіма налаштуваннями модуля й
        // перезаписує їх цілком, тож порожній POST стирає облікові дані каси.
        if ($this->request->method('post') && $this->request->post('sviat__checkbox__settings_form')) {
            $this->settings->set('sviat__checkbox__receipt_text', $this->request->post('sviat__checkbox__receipt_text'));
            $this->settings->set('sviat__checkbox__order_status_id', $this->request->post('sviat__checkbox__order_status_id', 'integer'));
            $this->settings->set('sviat__checkbox__send_message', $this->request->post('sviat__checkbox__send_message', 'integer'));
            $this->settings->set('sviat__checkbox__cashier_login', $this->request->post('sviat__checkbox__cashier_login'));
            $this->settings->set('sviat__checkbox__cashier_password', $this->request->post('sviat__checkbox__cashier_password'));
            $this->settings->set('sviat__checkbox__cashier_license_key', $this->request->post('sviat__checkbox__cashier_license_key'));
            $this->saveAdvanceSources();

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

        $this->design->assign('checkbox_advance_sources', CheckboxPaymentSources::rows(
            CheckboxPaymentSources::decode($this->settings->get(Init::ADVANCE_SOURCES))
        ));

        $this->design->assign('orders_statuses', $orderStatusEntity->find());
        $this->design->assign('is_nova_poshta_tracking_installed', $modules->isActiveModule('Sviat', 'NovaPoshtaTracking'));
        $this->design->assign('checkbox_installed_at', $installedAtInput);

        $this->response->setContent($this->design->fetch('checkbox_admin.tpl'));
    }

    /**
     * Джерела коштів, які пропонуватимуться при внесенні авансу.
     *
     * Засоби платежу з місцем під назву дають не один запис, а стільки, скільки
     * магазин завів платіжних систем: NovaPay і LiqPay — два різні джерела
     * одного шаблону.
     */
    private function saveAdvanceSources(): void
    {
        $enabled = $this->request->post('sviat__checkbox__source_enabled');
        $keys = is_array($enabled) ? array_keys($enabled) : [];

        // Назви платіжних систем читаються сирими: `post($name, 'string')`
        // вирізає дужки й лапки, а вибір типу теж не годиться — будь-який
        // непорожній $type згортає масив до першого елемента. Усе інше відсіює
        // CheckboxPaymentSources.
        $posted = $this->request->post('sviat__checkbox__source_name');
        $switches = $this->request->post('sviat__checkbox__source_on');
        $switches = is_array($switches) ? $switches : [];

        $names = [];
        if (is_array($posted)) {
            foreach ($posted as $base => $list) {
                if (!is_string($base) || !is_array($list)) {
                    continue;
                }
                // Пара name/on тримається на спільному індексі рядка, а не на
                // порядку: знятий прапорець не надсилається взагалі, і без
                // індексів масиви розійшлися б — вимкнули б чужий запис.
                foreach ($list as $index => $name) {
                    if (!is_string($name) || trim($name) === '') {
                        continue;
                    }
                    $names[$base][] = $name;
                    if (!empty($switches[$base][$index])) {
                        $keys[] = CheckboxPaymentCatalogue::compose($base, $name);
                    }
                }
            }
        }

        $this->settings->set(Init::ADVANCE_SOURCES, CheckboxPaymentSources::encode($keys, $names));
    }
}
