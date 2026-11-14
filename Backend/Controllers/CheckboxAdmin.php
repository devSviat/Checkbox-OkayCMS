<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\Checkbox\Carriers\ReceivedParcels;
use Okay\Entities\OrderStatusEntity;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxChainId;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentCatalogue;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentSources;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptDelivery;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use Okay\Modules\Sviat\Checkbox\Security\PlainSettingValue;
use Okay\Modules\Sviat\Checkbox\Security\RequestOrigin;
use Okay\Modules\Sviat\Checkbox\Security\ReturnUrl;

class CheckboxAdmin extends IndexAdmin
{
    public function fetch(
        CheckboxHelper $checkboxHelper,
        CashierShiftsEntity $shiftsEntity,
        OrderStatusEntity $orderStatusEntity,
        CheckboxApiHelper $apiHelper
    ) {
        // Не просто «був POST», а «прийшла саме ця форма». На сторінці є й чужі
        // форми з action="" — вихід із адмінки, наприклад, — і будь-яка з них
        // доходить сюди порожньою. Форма володіє всіма налаштуваннями модуля й
        // перезаписує їх цілком, тож порожній POST стирає облікові дані каси.
        if ($this->request->method('post') && $this->request->post('sviat__checkbox__settings_form')) {
            if (!RequestOrigin::isFromThisSite()) {
                $this->response->setStatusCode(403);
                $this->response->setContent(json_encode(['message' => 'Forbidden']), RESPONSE_JSON);

                return;
            }

            $this->saveTextSetting('sviat__checkbox__receipt_text');
            $this->settings->set('sviat__checkbox__order_status_id', $this->request->post('sviat__checkbox__order_status_id', 'integer'));
            $this->settings->set(Init::SEND_MESSAGE, CheckboxReceiptDelivery::mode($this->request->post(Init::SEND_MESSAGE)));
            $this->saveTextSetting('sviat__checkbox__cashier_login');
            $this->saveSecretSetting('sviat__checkbox__cashier_password');
            $this->saveSecretSetting('sviat__checkbox__cashier_license_key');
            $this->saveAdvanceSources();
            $this->settings->set(Init::REQUIRE_PAYMENT_METHOD, $this->request->post(Init::REQUIRE_PAYMENT_METHOD, 'integer') ? 1 : 0);

            // Префікс читається сирим: фільтр рядка лишає лише літери, цифри
            // та `_ - . %`, а двокрапку й решту пунктуації вирізає мовчки.
            // Чистить префікс CheckboxChainId — там же, де знає про його межі.
            $format = $this->request->post(Init::CHAIN_FORMAT);
            $this->settings->set(
                Init::CHAIN_FORMAT,
                $format === CheckboxChainId::FORMAT_DIGITS ? CheckboxChainId::FORMAT_DIGITS : CheckboxChainId::FORMAT_PREFIX
            );
            $this->settings->set(Init::CHAIN_PREFIX, CheckboxChainId::normalisePrefix($this->request->post(Init::CHAIN_PREFIX)));

            if (ServiceLocator::getInstance()->getService(ReceivedParcels::class)->available()) {
                $this->settings->set('sviat__checkbox__create_receipt_on_received', $this->request->post('sviat__checkbox__create_receipt_on_received', 'integer'));
            } else {
                // Якщо жодного перевізника немає — скидаємо налаштування, щоб cron не запускався
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

        // Баланс SMS лише кнопкою: кожен запит — вхід касиром у бойовий API,
        // а на відкритті сторінки відповідь здебільшого нікому не потрібна.
        $smsBalance = null;
        $smsCheckFailed = false;
        if ($this->request->method('post') && $this->request->post('sviat__checkbox__sms_check_form')) {
            if (!RequestOrigin::isFromThisSite()) {
                $this->response->setStatusCode(403);
                $this->response->setContent(json_encode(['message' => 'Forbidden']), RESPONSE_JSON);

                return;
            }
            $smsBalance = $apiHelper->phoneDeliveryStatus();
            $smsCheckFailed = $smsBalance === null;
        }

        // Оновлюємо статус CLOSING-змін перед відображенням
        foreach ($shiftsEntity->find(['status' => 'CLOSING']) as $closingShift) {
            $checkboxHelper->checkShiftStatus($closingShift->shift_id);
        }


        $installedAtRaw = $this->settings->get('sviat__checkbox__installed_at');
        $installedAtInput = !empty($installedAtRaw)
            ? date('Y-m-d\TH:i', strtotime($installedAtRaw))
            : '';

        $chainPrefix = CheckboxChainId::normalisePrefix($this->settings->get(Init::CHAIN_PREFIX));
        $this->design->assign('checkbox_chain_format', $this->settings->get(Init::CHAIN_FORMAT) === CheckboxChainId::FORMAT_DIGITS
            ? CheckboxChainId::FORMAT_DIGITS
            : CheckboxChainId::FORMAT_PREFIX);
        $this->design->assign('checkbox_chain_prefix', $chainPrefix);
        // Приклад рахує PHP тими самими правилами, що й бойовий шлях: доповнення
        // нулями неочевидне, і побачити його треба до збереження.
        $this->design->assign('checkbox_chain_example_prefix', CheckboxChainId::build(22674, 0, $chainPrefix));
        $this->design->assign('checkbox_chain_example_digits', CheckboxChainId::build(22674, 0, null));

        $this->design->assign('checkbox_advance_sources', CheckboxPaymentSources::rows(
            CheckboxPaymentSources::decode($this->settings->get(Init::ADVANCE_SOURCES))
        ));

        $this->design->assign('orders_statuses', $orderStatusEntity->find());
        $this->design->assign('is_carrier_available', ServiceLocator::getInstance()->getService(ReceivedParcels::class)->available());
        $this->design->assign('checkbox_installed_at', $installedAtInput);
        $this->design->assign('checkboxPasswordIsSet', (string) $this->settings->get('sviat__checkbox__cashier_password') !== '');
        $this->design->assign('checkboxLicenseIsSet', (string) $this->settings->get('sviat__checkbox__cashier_license_key') !== '');
        $this->design->assign('checkboxReturnUrl', ReturnUrl::safe($this->request->get('return')));
        $cashierConfigured = (string) $this->settings->get('sviat__checkbox__cashier_login') !== ''
            && (string) $this->settings->get('sviat__checkbox__cashier_password') !== ''
            && (string) $this->settings->get('sviat__checkbox__cashier_license_key') !== '';
        $this->design->assign('checkboxCashierConfigured', $cashierConfigured);
        $this->design->assign('checkboxActiveShift', $shiftsEntity->getActiveShift());

        $deliveryMode = CheckboxReceiptDelivery::mode($this->settings->get(Init::SEND_MESSAGE));
        $this->design->assign('checkboxDeliveryMode', $deliveryMode);
        // Блок телефону лишається видимим після натискання кнопки, хоч би який режим був збережений.
        $this->design->assign('checkboxPhoneMode', CheckboxReceiptDelivery::usesPhone($deliveryMode) || $smsBalance !== null || $smsCheckFailed);
        $this->design->assign('checkboxSmsBalance', $smsBalance?->hryvnias());
        $this->design->assign('checkboxSmsWarning', $smsBalance?->warning());
        $this->design->assign('checkboxSmsCheckFailed', $smsCheckFailed);

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

    private function saveTextSetting(string $name): void
    {
        $value = $this->request->post($name);
        if (PlainSettingValue::isPlain($value)) {
            $this->settings->set($name, $value);
        }
    }

    /** Секрети не виводяться у форму, тому порожнє поле означає «без змін». */
    private function saveSecretSetting(string $name): void
    {
        if ((string) $this->request->post($name) !== '') {
            $this->saveTextSetting($name);
        }
    }
}
