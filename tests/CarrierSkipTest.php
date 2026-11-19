<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPrepaymentHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * «Не відправляти до Checkbox» у рядку перевізника діє на кожному вході, як і на рядку способу оплати: спосіб
 * оплати сам чеки шле, а післяплату ROZETKA фіскалізує ЕВО. Сутності — анонімні класи: mappedBy() у Entity final.
 */
class CarrierSkipTest extends TestCase
{
    private const int STATUS_ID = 5;
    private const int CARRIER_DELIVERY = 23;
    private const int PICKUP_DELIVERY = 24;

    public function testPaidOrderSkippedByCarrierRow(): void
    {
        $receipts = self::receiptsSpy();
        $shifts = $this->createStub(CheckboxShiftsHelper::class);
        $opened = false;
        $shifts->method('openShiftIfNeeded')->willReturnCallback(function () use (&$opened) {
            $opened = true;

            return null;
        });

        $this->receiptsHelper(self::CARRIER_DELIVERY, $receipts, $shifts)->createReceiptsForPaidOrders([101], 1);

        self::assertFalse($receipts->written);
        self::assertFalse($opened);
    }

    public function testCardAndBulkStatusChangeSkippedByCarrierRow(): void
    {
        $sent = [];
        $extender = $this->extender($sent, self::CARRIER_DELIVERY);

        $extender->updateOrderStatus(true, (object)['id' => 101, 'payment_method_id' => 18, 'delivery_id' => self::CARRIER_DELIVERY], self::STATUS_ID);
        $extender->changeStatus(null, [101]);

        self::assertSame([], $sent);
    }

    /** Той самий спосіб оплати на самовивозі: рядок перевізника не діє, чек іде. */
    public function testOtherDeliveryUsesMethodRow(): void
    {
        $sent = [];
        $extender = $this->extender($sent, self::PICKUP_DELIVERY);

        $extender->updateOrderStatus(true, (object)['id' => 101, 'payment_method_id' => 18, 'delivery_id' => self::PICKUP_DELIVERY], self::STATUS_ID);
        $extender->changeStatus(null, [101]);

        self::assertSame([101, 101], $sent);
    }

    /** @return array<string, object> */
    private static function entities(int $deliveryId, object $receipts): array
    {
        $method = (object)[
            'id' => 18,
            'module' => 'null',
            Init::PAYMENT_SKIP_FIELD => 0,
            'sviat__rozetka__cod' => 1,
            Init::CARRIER_PAYMENTS_FIELD => '{"rozetka":{"type":"CASHLESS","label":"Платіж ROZETKAPAY","skip":true}}',
        ];
        $order = (object)['id' => 101, 'payment_method_id' => 18, 'delivery_id' => $deliveryId, 'status_id' => self::STATUS_ID];

        return [
            OrdersEntity::class => new class ($order) {
                public function __construct(private object $order)
                {
                }

                public function mappedBy($column)
                {
                    return $this;
                }

                public function find(array $filter = [])
                {
                    return [101 => $this->order];
                }

                public function update($id, $object)
                {
                    return $id;
                }
            },
            PaymentsEntity::class => new class ($method) {
                public function __construct(private object $method)
                {
                }

                public function mappedBy($column)
                {
                    return $this;
                }

                public function find(array $filter = [])
                {
                    return [18 => $this->method];
                }

                public function findOne(array $filter = [])
                {
                    return $this->method;
                }
            },
            DeliveriesEntity::class => new class {
                public function find(array $filter = [])
                {
                    return array_map(static fn(int $id): object => (object)['id' => $id, 'module_id' => $id === 23 ? 13 : 14], $filter['id']);
                }
            },
            ModulesEntity::class => new class {
                public function find(array $filter = [])
                {
                    return array_map(static fn(int $id): object => (object)['id' => $id, 'vendor' => $id === 13 ? 'Sviat' : 'OkayCMS', 'module_name' => $id === 13 ? 'RozetkaDelivery' : 'Pickup'], $filter['id']);
                }
            },
            PurchasesEntity::class => new class {
                public function count(array $filter = [])
                {
                    return 1;
                }
            },
            FiscalReceiptsEntity::class => $receipts,
        ];
    }

    private function settings(): Settings
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(static fn(string $name): string => $name === 'sviat__rozetka__cod_fiscal' ? 'shop' : (string)self::STATUS_ID);

        return $settings;
    }

    private function factory(int $deliveryId, object $receipts): EntityFactory
    {
        $entities = self::entities($deliveryId, $receipts);
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(static fn($class) => $entities[$class] ?? null);

        return $factory;
    }

    private function receiptsHelper(int $deliveryId, object $receipts, CheckboxShiftsHelper $shifts): CheckboxReceiptsHelper
    {
        $helper = new ReflectionClass(CheckboxReceiptsHelper::class)->newInstanceWithoutConstructor();
        $factory = $this->factory($deliveryId, $receipts);
        $settings = $this->settings();
        Closure::bind(function () use ($factory, $shifts, $settings) {
            $this->entityFactory = $factory;
            $this->shiftsHelper = $shifts;
            $this->settings = $settings;
            $this->accessToken = 'token';
            // Без цього хелпер пішов би по базу в ServiceLocator, і зламаний пропуск міг би лишитися зеленим.
            $this->receiptsTableReadable = true;
        }, $helper, CheckboxReceiptsHelper::class)();
        new ReflectionProperty(CheckboxReceiptsHelper::class, 'prepaymentHelper')
            ->setValue($helper, new ReflectionClass(CheckboxPrepaymentHelper::class)->newInstanceWithoutConstructor());

        return $helper;
    }

    /** @param list<int> $sent */
    private function extender(array &$sent, int $deliveryId): BackendExtender
    {
        $checkbox = $this->createStub(CheckboxHelper::class);
        $checkbox->method('createReceiptsForPaidOrders')->willReturnCallback(function (array $ids) use (&$sent) {
            array_push($sent, ...$ids);

            return null;
        });
        $extender = new ReflectionClass(BackendExtender::class)->newInstanceWithoutConstructor();
        $factory = $this->factory($deliveryId, self::receiptsSpy());
        $settings = $this->settings();
        Closure::bind(function () use ($factory, $checkbox, $settings) {
            $this->entityFactory = $factory;
            $this->checkboxHelper = $checkbox;
            $this->settings = $settings;
        }, $extender, BackendExtender::class)();

        return $extender;
    }

    private static function receiptsSpy(): object
    {
        return new class {
            public bool $written = false;

            public function order($order = null, array $additionalData = [])
            {
                return $this;
            }

            public function find(array $filter = [])
            {
                return [];
            }

            public function findOne(array $filter = [])
            {
                return false;
            }

            public function count(array $filter = [])
            {
                return 0;
            }

            public function add($object)
            {
                $this->written = true;

                return 1;
            }

            public function update($id, $object)
            {
                $this->written = true;

                return true;
            }
        };
    }
}
