<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\BackendTranslations;
use Okay\Core\EntityFactory;
use Okay\Core\ServiceLocator;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Helpers\OrdersHelper;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use ReflectionProperty;

/**
 * Порожній запис чека — рядок без receipt_id, який чекає на відкриту зміну.
 *
 * Два місця вирішують, який саме рядок вважати поточним, і вони мусять збігатися:
 * createReceipt() при закритій зміні та createReceiptsForPaidOrders() при
 * відкритій. Замовлення може тягнути хвіст старих порожніх записів, і тоді
 * розбіжність означає, що одне оновлює один рядок, а друге фіскалізує інший.
 *
 * Сутності підставляються анонімними класами, а не моками: order() в Entity
 * оголошений final, тож PHPUnit його не перекриває.
 */
class EmptyReceiptPlaceholderTest extends TestCase
{
    /** @var array<string, object|null> */
    private array $replacedServices = [];

    /** @var string|null ключ, який хелпер попросив у перекладів */
    private $requestedTranslation = null;

    protected function tearDown(): void
    {
        $this->restoreServices();
        parent::tearDown();
    }

    /**
     * Замовлення може тягнути хвіст порожніх записів, і оновлювати треба
     * найновіший, а не найстаріший.
     *
     * Перевіряється лише те, що хелпер просить `id_desc`. Що сам рядок дає
     * спадний порядок, тестом не закріплене: конструктор будь-якого Select бере
     * Database із ServiceLocator, а підміна того сервісу валить увесь прогін на
     * `Database::__destruct()`. Той самий контракт несе findLastSaleReceiptId().
     */
    public function testPlaceholderLookupTakesTheNewestRow(): void
    {
        $receipts = new class {
            /** @var string|null */
            public $ordered = null;

            public function order($order = null, array $additionalData = [])
            {
                $this->ordered = $order;

                return $this;
            }

            public function findOne(array $filter = [])
            {
                // Дефолтний порядок сутності — id за зростанням, тобто найстаріший.
                return (object)['id' => $this->ordered === 'id_desc' ? 36630 : 8172];
            }
        };

        $helper = $this->buildHelper([FiscalReceiptsEntity::class => $receipts]);

        $method = new ReflectionMethod(CheckboxReceiptsHelper::class, 'findEmptyReceiptId');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }

        self::assertSame(36630, $method->invoke($helper, 14665, false));
        self::assertSame('id_desc', $receipts->ordered, 'без явного порядку взявся б найстаріший рядок');
    }

    /**
     * Спосіб оплати з «не надсилати в Checkbox» не має лишати по собі порожній
     * запис: чек за ним не поїде ніколи, а createReceiptsForPaidOrders()
     * відсіює такі замовлення за тим самим полем — рядок завис би назавжди.
     */
    public function testPaymentMethodMarkedSkipNeverReachesTheClosedShiftBranch(): void
    {
        $shifts = new class {
            public bool $asked = false;

            public function count(array $filter = [])
            {
                $this->asked = true;

                return 0;
            }
        };

        $receipts = new class {
            public bool $written = false;

            public function order($order = null, array $additionalData = [])
            {
                return $this;
            }

            public function findOne(array $filter = [])
            {
                return false;
            }

            public function add($object)
            {
                $this->written = true;

                return 1;
            }
        };

        $orders = new class {
            public function get($id)
            {
                return (object)['id' => 14665, 'payment_method_id' => 3];
            }
        };

        $payments = new class {
            public function findOne(array $filter = [])
            {
                return (object)['id' => 3, Init::PAYMENT_SKIP_FIELD => 1];
            }
        };

        $ordersHelper = $this->createStub(OrdersHelper::class);
        $ordersHelper->method('getOrderPurchasesList')->willReturn([(object)['id' => 1]]);
        $this->replaceService(OrdersHelper::class, $ordersHelper);

        $helper = $this->buildHelper([
            OrdersEntity::class         => $orders,
            PaymentsEntity::class       => $payments,
            CashierShiftsEntity::class  => $shifts,
            FiscalReceiptsEntity::class => $receipts,
        ]);

        $result = $helper->createReceipt(14665);

        self::assertSame('переклад', $result->message);
        self::assertSame('sviat__checkbox__payment_method_dont_send', $this->requestedTranslation);
        self::assertFalse($receipts->written, 'порожній запис для PAYMENT_SKIP не має створюватись');
        self::assertFalse($shifts->asked, 'перевірка зміни зайва — рішення вже ухвалене');
    }

    /** @param array<string, object> $entities FQCN сутності → підставлений об'єкт */
    private function buildHelper(array $entities): CheckboxReceiptsHelper
    {
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(
            function ($class) use ($entities) {
                return $entities[$class] ?? null;
            }
        );

        // Конструктор ходить у ServiceLocator по налаштування Checkbox, а тут
        // перевіряються рішення, ухвалені до першого звернення до API.
        $helper = (new ReflectionClass(CheckboxReceiptsHelper::class))->newInstanceWithoutConstructor();

        $translations = $this->createStub(BackendTranslations::class);
        $translations->method('getTranslation')->willReturnCallback(
            function ($key) {
                $this->requestedTranslation = $key;

                return 'переклад';
            }
        );

        Closure::bind(
            function () use ($factory, $translations) {
                $this->entityFactory = $factory;
                $this->translations = $translations;
                $this->accessToken = 'token';
            },
            $helper,
            CheckboxReceiptsHelper::class
        )();

        return $helper;
    }

    /**
     * Підміняє сервіс у контейнері: createReceipt() дістає OrdersHelper не з
     * конструктора, а сам, тож без підміни DI піднімав би справжнє з'єднання.
     */
    private function replaceService(string $id, object $service): void
    {
        $container = $this->container();

        $store = $this->reflect($container, 'serviceStore');
        $current = $store->getValue($container);

        if (!array_key_exists($id, $this->replacedServices)) {
            $this->replacedServices[$id] = $current[$id] ?? null;
        }

        $current[$id] = $service;
        $store->setValue($container, $current);
    }

    private function restoreServices(): void
    {
        if (!$this->replacedServices) {
            return;
        }

        $container = $this->container();

        $store = $this->reflect($container, 'serviceStore');
        $current = $store->getValue($container);

        foreach ($this->replacedServices as $id => $previous) {
            if ($previous === null) {
                unset($current[$id]);
            } else {
                $current[$id] = $previous;
            }
        }

        $store->setValue($container, $current);
        $this->replacedServices = [];
    }

    private function container(): object
    {
        $locator = ServiceLocator::getInstance();

        return $this->reflect(ServiceLocator::class, 'DI')->getValue($locator);
    }

    /**
     * До PHP 8.1 читання приватної властивості вимагає setAccessible(), а з 8.1
     * той самий виклик задепрекейчено. Модуль їде на обох рушіях.
     *
     * @param object|string $target
     */
    private function reflect($target, string $name): ReflectionProperty
    {
        $property = new ReflectionProperty($target, $name);
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }

        return $property;
    }
}
