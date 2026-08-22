<?php

namespace Okay\Modules\Sviat\Checkbox\Init;

use Okay\Core\Database;
use Okay\Core\QueryFactory;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;
use Okay\Helpers\OrdersHelper;
use Okay\Entities\OrdersEntity;
use Okay\Core\Scheduler\Schedule;
use Okay\Entities\ManagersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Core\Modules\EntityField;
use Okay\Core\Modules\AbstractInit;
use Okay\Admin\Helpers\BackendMainHelper;
use Okay\Admin\Helpers\BackendOrdersHelper;
use Okay\Admin\Helpers\BackendProductsHelper;
use Okay\Admin\Requests\BackendPaymentsRequest;
use Okay\Admin\Requests\BackendProductsRequest;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Extenders\FrontExtender;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;

class Init extends AbstractInit
{
    public const PAYMENT_TYPE_FIELD  = 'sviat__checkbox__payment_type';
    public const PAYMENT_LABEL_FIELD = 'sviat__checkbox__payment_label';
    public const PAYMENT_SKIP_FIELD  = 'sviat__checkbox__payment_skip';

    public const PRODUCT_TAX_GROUPS_TABLE = '__sviat__checkbox__product_tax_groups';

    public const CASHIER_LOGIN       = 'sviat__checkbox__cashier_login';
    public const CASHIER_PASSWORD    = 'sviat__checkbox__cashier_password';
    public const CASHIER_LICENSE_KEY = 'sviat__checkbox__cashier_license_key';

    public function install()
    {
        $this->setBackendMainController('CheckboxAdmin');

        $this->migrateEntityTable(TaxGroupsEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('code'))->setTypeInt(11, false)->setIndex(),
            (new EntityField('name'))->setTypeVarchar(255, false),
        ]);

        $this->migrateEntityTable(FiscalReceiptsEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('order_id'))->setTypeInt(11, false),
            (new EntityField('receipt_id'))->setTypeVarchar(64, false),
            (new EntityField('related_receipt_id'))->setTypeVarchar(64, true),
            (new EntityField('is_return'))->setTypeInt(1, false)->setDefault('0'),
            (new EntityField('sent'))->setTypeDatetime(true),
            (new EntityField('created_at'))->setTypeTimestamp(true, null),
            (new EntityField('updated_at'))->setTypeTimestamp(true, null),
        ]);

        $this->migrateEntityField(
            PaymentsEntity::class,
            (new EntityField(self::PAYMENT_SKIP_FIELD))->setTypeTinyInt(1, false)->setDefault('0')
        );
        $this->migrateEntityField(
            PaymentsEntity::class,
            (new EntityField(self::PAYMENT_TYPE_FIELD))
                ->setTypeEnum(['CASH', 'CARD', 'CASHLESS', 'OTHER'], false)
                ->setDefault('CASH')
        );

        $this->migrateCustomTable(self::PRODUCT_TAX_GROUPS_TABLE, [
            (new EntityField('product_id'))->setTypeInt(11, false)->setIndex(),
            (new EntityField('tax_id'))->setTypeInt(11, false)->setIndex(),
        ]);

        $this->migrateEntityTable(CashierShiftsEntity::class, [
            (new EntityField('id'))->setIndexPrimaryKey()->setTypeInt(11, false)->setAutoIncrement(),
            (new EntityField('shift_id'))->setTypeVarchar(64, false),
            (new EntityField('serial'))->setTypeInt(11, false)->setDefault('0'),
            (new EntityField('status'))->setTypeVarchar(32, false),
            (new EntityField('shift_report_id'))->setTypeVarchar(64, false)->setDefault(''),
            (new EntityField('opened_at'))->setTypeTimestamp(true, null),
            (new EntityField('closed_at'))->setTypeTimestamp(true, null),
        ]);

        // Складений PK для таблиці зв'язку — додається вручну. Видалення
        // модуля не відкочує міграцій, тож при повторній установці таблиця вже
        // з ключем: без перевірки це 1068 Multiple primary key defined у лог.
        $this->addProductTaxGroupsPrimaryKey();

        $this->migrateEntityField(
            ManagersEntity::class,
            (new EntityField(self::CASHIER_LOGIN))->setTypeVarchar(255, true)
        );
        $this->migrateEntityField(
            PaymentsEntity::class,
            (new EntityField(self::PAYMENT_LABEL_FIELD))->setTypeVarchar(255)
        );

        // Фіксується один раз — нижня межа пошуку ТТН для автовиставлення чеків
        $settings = ServiceLocator::getInstance()->getService(Settings::class);
        if (!$settings->get('sviat__checkbox__installed_at')) {
            $settings->set('sviat__checkbox__installed_at', date('Y-m-d H:i:s'));
        }
    }

    public function init()
    {
        $this->addPermission('sviat__checkbox');

        $this->registerBackendController('CheckboxAdmin');
        $this->addBackendControllerPermission('CheckboxAdmin', 'sviat__checkbox');
        $this->registerBackendController('CashierShiftsAdmin');
        $this->addBackendControllerPermission('CashierShiftsAdmin', 'sviat__checkbox');
        $this->registerBackendController('TaxGroupAdmin');
        $this->addBackendControllerPermission('TaxGroupAdmin', 'sviat__checkbox');
        $this->registerBackendController('TaxGroupsAdmin');
        $this->addBackendControllerPermission('TaxGroupsAdmin', 'sviat__checkbox');

        $this->registerEntityField(PaymentsEntity::class, self::PAYMENT_SKIP_FIELD);
        $this->registerEntityField(PaymentsEntity::class, self::PAYMENT_LABEL_FIELD);
        $this->registerEntityField(PaymentsEntity::class, self::PAYMENT_TYPE_FIELD);

        $this->addBackendBlock('payment_custom_block', 'fiscal_receipt_payment_type_block.tpl');
        $this->addBackendBlock('product_relations', 'fiscal_receipt_product_taxes.tpl');
        $this->addBackendBlock('orders_list_name', 'fiscal_receipt_order_last_receipt.tpl');
        $this->addBackendBlock('order_custom_block', 'fiscal_receipt_order_block.tpl');

        $this->registerQueueExtension(
            ['class' => BackendOrdersHelper::class, 'method' => 'changeStatus'],
            ['class' => BackendExtender::class,     'method' => 'changeStatus']
        );
        $this->registerQueueExtension(
            ['class' => BackendOrdersHelper::class, 'method' => 'updateOrderStatus'],
            ['class' => BackendExtender::class,     'method' => 'updateOrderStatus']
        );
        $this->registerQueueExtension(
            ['class' => OrdersEntity::class,    'method' => 'markedPaid'],
            ['class' => BackendExtender::class, 'method' => 'orderMarkedPaid']
        );
        $this->registerQueueExtension(
            ['class' => BackendMainHelper::class, 'method' => 'evensCounters'],
            ['class' => BackendExtender::class,   'method' => 'initializeFiscalReceipt']
        );
        $this->registerQueueExtension(
            ['class' => BackendPaymentsRequest::class, 'method' => 'postPayment'],
            ['class' => BackendExtender::class,        'method' => 'postPayment']
        );
        $this->registerQueueExtension(
            ['class' => BackendProductsHelper::class,  'method' => 'getProduct'],
            ['class' => BackendExtender::class,        'method' => 'getProduct']
        );
        $this->registerQueueExtension(
            ['class' => BackendProductsRequest::class, 'method' => 'postProduct'],
            ['class' => BackendExtender::class,        'method' => 'postProduct']
        );
        $this->registerQueueExtension(
            ['class' => BackendOrdersHelper::class, 'method' => 'findOrders'],
            ['class' => BackendExtender::class,     'method' => 'findOrders']
        );
        $this->registerQueueExtension(
            ['class' => BackendOrdersHelper::class, 'method' => 'findOrder'],
            ['class' => BackendExtender::class,     'method' => 'initializeOrderReceipts']
        );
        $this->registerQueueExtension(
            ['class' => OrdersHelper::class,  'method' => 'getOrderPurchasesList'],
            ['class' => FrontExtender::class, 'method' => 'getOrderPurchasesList']
        );

        // Cron: чеки для отриманих ТТН — через 2 хв після оновлення трекінгу
        $this->registerSchedule(
            (new Schedule([CheckboxHelper::class, 'processReceivedOrders']))
                ->name('Process received orders and create fiscal receipts')
                ->time('2,12,22,32,42,52 * * * *')
                ->overlap(false)
                ->timeout(600)
        );
        // Cron: закриття застарілих змін, оновлення CLOSING-статусів
        $this->registerSchedule(
            (new Schedule([CheckboxHelper::class, 'cronCheckShifts']))
                ->name('Check cashier shifts status')
                ->time('*/5 * * * *')
                ->overlap(false)
                ->timeout(300)
        );
        // Cron: повторна відправка чеків, що лягли в БД без receipt_id.
        // Зсунуто відносно перевірки змін: обидві задачі ходять в Checkbox.
        $this->registerSchedule(
            (new Schedule([CheckboxHelper::class, 'checkEmptyReceipts']))
                ->name('Resend fiscal receipts saved without receipt_id')
                ->time('3,13,23,33,43,53 * * * *')
                ->overlap(false)
                ->timeout(300)
        );

        $this->extendBackendMenu(
            'sviat__left_checkbox',
            [
                'sviat__left_checkbox_settings' => ['CheckboxAdmin'],
                'sviat__left_checkbox_taxes'    => ['TaxGroupsAdmin', 'TaxGroupAdmin'],
                'sviat__left_checkbox_shifts'   => ['CashierShiftsAdmin'],
            ],
            '<svg width="20" height="20" viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg">
                <path d="M9.80953 18C11.7386 18 13.3377 17.4956 14.6066 16.4654C15.8756 15.4353 16.6699 14.2549 17 12.9027L13.5749 12.0013C13.3686 12.7096 12.9251 13.3105 12.2442 13.8149C11.5633 14.3193 10.738 14.5661 9.76822 14.5661C8.53023 14.5661 7.51926 14.1153 6.74556 13.2139C5.97185 12.3125 5.59007 11.2395 5.59007 9.97316C5.59007 8.70694 5.97185 7.62302 6.7352 6.74316C7.49866 5.8632 8.50963 5.42321 9.76822 5.42321C10.7276 5.42321 11.553 5.69145 12.2442 6.20659C12.9251 6.7324 13.3686 7.34402 13.5749 8.04158L17 7.16161C16.6699 5.80957 15.8756 4.60764 14.5963 3.56669C13.3171 2.52582 11.7077 2 9.74762 2C7.53986 2 5.69328 2.77263 4.21802 4.29644C2.73246 5.831 2 7.73038 2 9.99458C2 12.2911 2.73246 14.1905 4.21802 15.7143C5.70353 17.2381 7.56057 18 9.80953 18Z" fill="url(#left_checkbox_linear)"/>
                <defs>
                <linearGradient id="left_checkbox_linear" x1="3.13067" y1="17.6888" x2="25.6398" y2="-3.95052" gradientUnits="userSpaceOnUse">
                <stop stop-color="currentColor"/>
                <stop offset="1" stop-color="currentColor" stop-opacity="0"/>
                </linearGradient>
                </defs>
            </svg>'
        );
    }

    /** Складений PK на таблиці звʼязку, якщо його там ще немає. */
    private function addProductTaxGroupsPrimaryKey(): void
    {
        if ($this->hasPrimaryKey(self::PRODUCT_TAX_GROUPS_TABLE)) {
            return;
        }

        $sl = ServiceLocator::getInstance();
        $sql = $sl->getService(QueryFactory::class)->newSqlQuery();
        $sql->setStatement('ALTER TABLE ' . self::PRODUCT_TAX_GROUPS_TABLE . ' ADD PRIMARY KEY (`product_id`,`tax_id`)');
        $sl->getService(Database::class)->query($sql);
    }

    private function hasPrimaryKey(string $table): bool
    {
        $sl = ServiceLocator::getInstance();
        $sql = $sl->getService(QueryFactory::class)->newSqlQuery();
        $sql->setStatement("SHOW INDEX FROM `{$table}` WHERE Key_name = 'PRIMARY'");

        if ($sl->getService(Database::class)->query($sql) !== true) {
            return false;
        }

        return $sl->getService(Database::class)->results() !== [];
    }
}
