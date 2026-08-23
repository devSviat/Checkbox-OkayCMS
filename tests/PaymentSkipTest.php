<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Спосіб оплати, позначений «не відправляти до Checkbox», не має давати чека
 * **з жодного** входу.
 *
 * Входів чотири, і вони незалежні: оплата замовлення, зміна статусу в картці,
 * масова зміна статусу зі списку та кнопка в картці. Гард стоїть у кожному
 * окремо, тож перевіряти треба кожен: пропуск в одному не видно з інших, а
 * ціна помилки — чек у податковій за операцію, якої не було.
 *
 * Сутності підставляються анонімними класами: mappedBy() і order() в Entity
 * оголошені final, тож PHPUnit їх не перекриває.
 */
class PaymentSkipTest extends TestCase
{
    private const SKIPPED_PAYMENT_ID = 14;
    private const STATUS_ID = 5;

    /**
     * Оплата замовлення. Тут важливий не лише чек: зміна не має навіть
     * відкритися — відкриття зміни саме собою фіскальна операція.
     */
    public function testPaidOrderWithSkippedPaymentCreatesNothing(): void
    {
        $receipts = $this->receiptsSpy();
        $shifts = $this->createStub(CheckboxShiftsHelper::class);
        $opened = false;
        $shifts->method('openShiftIfNeeded')->willReturnCallback(
            function () use (&$opened) {
                $opened = true;

                return null;
            }
        );

        $helper = $this->receiptsHelper([
            OrdersEntity::class         => $this->ordersMap(),
            PurchasesEntity::class      => $this->purchasesCounter(),
            FiscalReceiptsEntity::class => $receipts,
            PaymentsEntity::class       => $this->paymentsMap(),
        ], $shifts);

        $helper->createReceiptsForPaidOrders([101], 1);

        self::assertFalse($receipts->written, 'чек не має створюватись');
        self::assertFalse($opened, 'зміна не має відкриватись заради замовлення, яке пропускаємо');
    }

    /** Зміна статусу в картці замовлення. */
    public function testOrderCardStatusChangeSkipsTheOrder(): void
    {
        $sent = [];
        $extender = $this->extender($sent, [
            CashierShiftsEntity::class => $this->openShift(),
            PaymentsEntity::class      => $this->paymentsSingle(),
        ]);

        $order = (object)['id' => 101, 'payment_method_id' => self::SKIPPED_PAYMENT_ID];
        $extender->updateOrderStatus(true, $order, self::STATUS_ID);

        self::assertSame([], $sent);
    }

    /** Масова зміна статусу зі списку замовлень. */
    public function testBulkStatusChangeSkipsTheOrder(): void
    {
        $sent = [];
        $extender = $this->extender($sent, [
            CashierShiftsEntity::class  => $this->openShift(),
            OrdersEntity::class         => $this->ordersMap(),
            PaymentsEntity::class       => $this->paymentsMap(),
            FiscalReceiptsEntity::class => $this->receiptsSpy(),
        ]);

        $extender->changeStatus(null, [101]);

        self::assertSame([], $sent);
    }

    /**
     * Замовлення без способу оплати гард не зупиняє — прапорця там немає, і
     * пропускати нема за чим. Зафіксовано, щоб зміна цієї поведінки була
     * свідомою: у базі такі замовлення є.
     */
    public function testOrderWithoutPaymentMethodIsNotSkipped(): void
    {
        $sent = [];
        $extender = $this->extender($sent, [
            CashierShiftsEntity::class  => $this->openShift(),
            OrdersEntity::class         => $this->ordersMap(0),
            PaymentsEntity::class       => $this->paymentsMap(),
            FiscalReceiptsEntity::class => $this->receiptsSpy(),
        ]);

        $extender->changeStatus(null, [101]);

        self::assertSame([101], $sent);
    }

    // ── заглушки ──────────────────────────────────────────────────────────

    /** Мапа замовлень для mappedBy('id')->find() у хелпері й екстендері. */
    private function ordersMap(int $paymentMethodId = self::SKIPPED_PAYMENT_ID): object
    {
        return new class ($paymentMethodId) {
            private int $paymentMethodId;

            public function __construct(int $paymentMethodId)
            {
                $this->paymentMethodId = $paymentMethodId;
            }

            public function mappedBy($column)
            {
                return $this;
            }

            public function find(array $filter = [])
            {
                return [101 => (object)[
                    'id'                => 101,
                    'payment_method_id' => $this->paymentMethodId,
                    'status_id'         => PaymentSkipTest::statusId(),
                ]];
            }
        };
    }

    private function paymentsMap(): object
    {
        return new class {
            public function mappedBy($column)
            {
                return $this;
            }

            public function find(array $filter = [])
            {
                return [PaymentSkipTest::skippedPaymentId() => (object)[
                    'id'                      => PaymentSkipTest::skippedPaymentId(),
                    Init::PAYMENT_SKIP_FIELD  => 1,
                ]];
            }
        };
    }

    private function paymentsSingle(): object
    {
        return new class {
            public function findOne(array $filter = [])
            {
                return (object)[
                    'id'                     => PaymentSkipTest::skippedPaymentId(),
                    Init::PAYMENT_SKIP_FIELD => 1,
                ];
            }
        };
    }

    private function openShift(): object
    {
        return new class {
            public function getActiveShift()
            {
                return (object)['id' => 1, 'status' => 'OPENED', 'shift_id' => 'shift'];
            }
        };
    }

    private function purchasesCounter(): object
    {
        return new class {
            public function count(array $filter = [])
            {
                return 1;
            }
        };
    }

    /** Ловить будь-яку спробу записати чек. */
    private function receiptsSpy(): object
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

    /** @param array<string, object> $entities */
    private function receiptsHelper(array $entities, CheckboxShiftsHelper $shifts): CheckboxReceiptsHelper
    {
        $helper = (new ReflectionClass(CheckboxReceiptsHelper::class))->newInstanceWithoutConstructor();
        $factory = $this->factory($entities);

        Closure::bind(
            function () use ($factory, $shifts) {
                $this->entityFactory = $factory;
                $this->shiftsHelper = $shifts;
                $this->accessToken = 'token';
            },
            $helper,
            CheckboxReceiptsHelper::class
        )();

        return $helper;
    }

    /**
     * @param list<int>              $sent  сюди лягають id, на які пішов чек
     * @param array<string, object>  $entities
     */
    private function extender(array &$sent, array $entities): BackendExtender
    {
        $checkbox = $this->createStub(CheckboxHelper::class);
        $checkbox->method('createReceipt')->willReturnCallback(
            function ($orderId) use (&$sent) {
                $sent[] = $orderId;

                return [];
            }
        );

        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn((string)self::STATUS_ID);

        $extender = (new ReflectionClass(BackendExtender::class))->newInstanceWithoutConstructor();
        $factory = $this->factory($entities);

        // design і request лишаються невизначеними свідомо: шляхи зміни статусу
        // їх не читають, а Request не піддається дублюванню — у нього є власний
        // метод method().
        Closure::bind(
            function () use ($factory, $checkbox, $settings) {
                $this->entityFactory = $factory;
                $this->checkboxHelper = $checkbox;
                $this->settings = $settings;
            },
            $extender,
            BackendExtender::class
        )();

        return $extender;
    }

    /** @param array<string, object> $entities */
    private function factory(array $entities): EntityFactory
    {
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(
            function ($class) use ($entities) {
                return $entities[$class] ?? null;
            }
        );

        return $factory;
    }

    public static function skippedPaymentId(): int
    {
        return self::SKIPPED_PAYMENT_ID;
    }

    public static function statusId(): int
    {
        return self::STATUS_ID;
    }
}
