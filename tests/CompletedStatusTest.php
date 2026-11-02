<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Статус із налаштувань означає «гроші отримано»: замовлення стає оплаченим, а чек
 * і зміну далі веде звичайний шлях оплати. Картка викликає хук при кожному збереженні,
 * тож реагувати можна лише на перехід у статус.
 */
class CompletedStatusTest extends TestCase
{
    private const int DONE = 4;
    private const int OTHER = 2;
    private const int SKIPPED_METHOD = 27;

    /** @return object{extender: BackendExtender, orders: object, sent: \ArrayObject} */
    private function env(int $setting = self::DONE, int $statusInDb = self::OTHER, int $method = 18): object
    {
        $orders = new class ($statusInDb, $method) {
            public array $paidIds = [];
            public function __construct(public int $status, public int $method) {}
            public function cols(array $cols) { return $this; }
            public function mappedBy($column) { return $this; }
            public function get($id) { return (object) ['id' => (int) $id, 'status_id' => $this->status, 'payment_method_id' => $this->method]; }
            public function find(array $filter = []) { return [101 => $this->get(101)]; }
            public function update($id, $object) { $this->paidIds[] = (int) $id; return $id; }
        };
        $payments = new class {
            public function findOne(array $filter = []) { return (object) ['id' => $filter['id'], Init::PAYMENT_SKIP_FIELD => (int) ($filter['id'] === CompletedStatusTest::skipped())]; }
            public function mappedBy($column) { return $this; }
            public function find(array $filter = []) { return [CompletedStatusTest::skipped() => (object) ['id' => CompletedStatusTest::skipped(), Init::PAYMENT_SKIP_FIELD => 1]]; }
        };

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn ($class) => [OrdersEntity::class => $orders, PaymentsEntity::class => $payments][$class]);

        $sent = new \ArrayObject();
        $checkbox = $this->createStub(CheckboxHelper::class);
        $checkbox->method('createReceiptsForPaidOrders')->willReturnCallback(function (array $ids) use ($sent) {
            foreach ($ids as $id) {
                $sent[] = $id;
            }
            return null;
        });

        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn((string) $setting);

        $extender = new ReflectionClass(BackendExtender::class)->newInstanceWithoutConstructor();
        Closure::bind(function () use ($factory, $checkbox, $settings) {
            $this->entityFactory = $factory;
            $this->checkboxHelper = $checkbox;
            $this->settings = $settings;
        }, $extender, BackendExtender::class)();

        return (object) ['extender' => $extender, 'orders' => $orders, 'sent' => $sent];
    }

    public static function skipped(): int
    {
        return self::SKIPPED_METHOD;
    }

    private function card(object $env, bool $saved = true, int $status = self::DONE): void
    {
        $order = (object) ['id' => 101, 'payment_method_id' => $env->orders->method];
        $env->extender->rememberOrderStatus($order);
        $env->orders->status = $status;
        $env->extender->updateOrderStatus($saved, $order, $status);
    }

    public function testMovingToTheStatusMarksPaidAndIssuesReceipt(): void
    {
        $env = $this->env();

        $this->card($env);

        self::assertSame([101], $env->orders->paidIds);
        self::assertSame([101], (array) $env->sent);
    }

    /** Редагування телефону в уже завершеному замовленні не має ставити оплату. */
    public function testResavingAnOrderAlreadyInTheStatusDoesNothing(): void
    {
        $env = $this->env(statusInDb: self::DONE);

        $this->card($env);

        self::assertSame([], $env->orders->paidIds);
        self::assertSame([], (array) $env->sent);
    }

    public function testOtherStatusDoesNothing(): void
    {
        $env = $this->env();

        $this->card($env, status: 3);

        self::assertSame([], $env->orders->paidIds);
    }

    public function testDisabledSettingDoesNothing(): void
    {
        $env = $this->env(setting: 0);

        $this->card($env);

        self::assertSame([], $env->orders->paidIds);
    }

    /** Ядро відмовило закрити замовлення, наприклад через залишки: статус не змінився. */
    public function testFailedStatusChangeDoesNothing(): void
    {
        $env = $this->env();

        $this->card($env, saved: false);

        self::assertSame([], $env->orders->paidIds);
    }

    public function testMethodWithoutReceiptsIsNotMarkedPaid(): void
    {
        $env = $this->env(method: self::SKIPPED_METHOD);

        $this->card($env);

        self::assertSame([], $env->orders->paidIds);
        self::assertSame([], (array) $env->sent);
    }

    public function testNewOrderHasNoPreviousStatus(): void
    {
        $env = $this->env();

        $env->extender->updateOrderStatus(true, (object) ['id' => 101, 'payment_method_id' => 18], self::DONE);

        self::assertSame([101], $env->orders->paidIds);
    }

    public function testBulkChangeMarksPaidAndIssuesReceipt(): void
    {
        $env = $this->env(statusInDb: self::DONE);

        $env->extender->changeStatus(null, [101]);

        self::assertSame([101], $env->orders->paidIds);
        self::assertSame([101], (array) $env->sent);
    }

    public function testSettingExplainsWhatTheStatusDoes(): void
    {
        $module = __DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox';
        $template = (string) file_get_contents($module . '/Backend/design/html/checkbox_admin.tpl');

        self::assertStringContainsString('sviat__checkbox__order_status_hint|escape', $template);

        foreach (['ua', 'en', 'ru'] as $language) {
            $lang = [];
            require $module . "/Backend/lang/$language.php";
            self::assertNotEmpty($lang['sviat__checkbox__order_status_hint'] ?? null, $language);
        }
    }

    public function testBulkChangeSkipsMethodWithoutReceipts(): void
    {
        $env = $this->env(statusInDb: self::DONE, method: self::SKIPPED_METHOD);

        $env->extender->changeStatus(null, [101]);

        self::assertSame([], $env->orders->paidIds);
    }
}
