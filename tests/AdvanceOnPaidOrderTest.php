<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\BackendTranslations;
use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentForm;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use PHPUnit\Framework\TestCase;

/**
 * Аванс на повністю оплаченому замовленні.
 *
 * Аванс — це частина суми, якої ще не отримано: рівну вартості товарів Checkbox
 * відхилить сам, а менша лишить ланцюжок відкритим на гроші, що вже надійшли.
 * Кнопку картка ховає (CheckboxCardActions), але ховання кнопки захистом не є —
 * ajax-ендпоінт доступний і зі старої вкладки, і з другої сесії менеджера.
 */
class AdvanceOnPaidOrderTest extends TestCase
{
    public function testAdvanceIsRefusedOnAPaidOrder(): void
    {
        $helper = $this->helperForOrder(['paid' => 1]);

        $result = $helper->createPrepaymentReceipt(22991, 50000, CheckboxPaymentForm::SOURCE_CASH);

        self::assertIsObject($result, 'аванс на оплаченому замовленні не відмовлено');
        self::assertSame('ПЕРЕКЛАД:sviat__checkbox__errors_order_paid', $result->message);
    }

    /**
     * Контроль: без позначки оплати аванс мусить іти своїм шляхом. Без нього
     * тест вище лишався б зеленим і тоді, коли метод відмовляє геть усім.
     */
    public function testAdvanceProceedsWhenOrderIsNotPaid(): void
    {
        self::assertNotSame(
            'ПЕРЕКЛАД:sviat__checkbox__errors_order_paid',
            $this->outcomeFor(['paid' => 0]),
            'неоплачене замовлення відмовлено як оплачене'
        );
    }

    /**
     * Прапорець приходить із бази рядком: `!empty("0")` — false. Перевірка
     * мусить пускати таке замовлення далі, а не рахувати його оплаченим.
     */
    public function testZeroAsStringIsNotTreatedAsPaid(): void
    {
        self::assertNotSame(
            'ПЕРЕКЛАД:sviat__checkbox__errors_order_paid',
            $this->outcomeFor(['paid' => '0']),
            '"0" з бази порахували за оплату'
        );
    }

    /**
     * Чим завершився виклик — байдуже: за перевіркою йдуть етапи, яким потрібні
     * і база, і сервіси, а в CI бази немає. Питання тут одне: чи спрацював
     * прапорець оплати, і відповідь на нього однакова в обох середовищах.
     *
     * @param array<string, mixed> $orderFields
     */
    private function outcomeFor(array $orderFields): string
    {
        try {
            $result = $this->helperForOrder($orderFields)
                ->createPrepaymentReceipt(22991, 50000, CheckboxPaymentForm::SOURCE_CASH);
        } catch (\Throwable $e) {
            return 'пішов далі: ' . $e->getMessage();
        }

        return is_object($result) && isset($result->message) ? (string)$result->message : 'пішов далі';
    }

    /** @param array<string, mixed> $orderFields */
    private function helperForOrder(array $orderFields): CheckboxReceiptsHelper
    {
        $helper = new class extends CheckboxReceiptsHelper {
            public function __construct()
            {
                // Батьківський конструктор іде в ServiceLocator по налаштування.
            }

            /** @return array|false */
            public function getAccessToken()
            {
                $this->accessToken = 'working-token';

                return ['access_token' => 'working-token'];
            }

            public function orderChainStatus(int $orderId): ?array
            {
                return null;
            }
        };

        $receipts = new class {
            public function find(array $filter = [])
            {
                return [];
            }
        };

        $orders = new class {
            /** @var object */
            public $order;

            public function get($id)
            {
                return $this->order;
            }
        };
        $orders->order = (object)($orderFields + ['id' => 22991]);

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(
            function ($class) use ($receipts, $orders) {
                return $class === OrdersEntity::class ? $orders : $receipts;
            }
        );

        $translations = $this->createStub(BackendTranslations::class);
        $translations->method('getTranslation')->willReturnCallback(
            function ($key) {
                return 'ПЕРЕКЛАД:' . $key;
            }
        );

        Closure::bind(
            function () use ($factory, $translations) {
                $this->entityFactory = $factory;
                $this->translations = $translations;
            },
            $helper,
            CheckboxApiHelper::class
        )();

        return $helper;
    }
}
