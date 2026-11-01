<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Відкрита зміна — це фіскальна подія зі своїм Z-звітом. Без цього відбору
 * замовлення, якому нічого не треба, щоночі відкривало б порожню зміну.
 */
class ShiftOpensOnlyForRealReceiptTest extends TestCase
{
    private const int SKIP_METHOD = 2;

    public function testFullPaidChainDoesNotOpenShift(): void
    {
        $run = $this->fiscaliseBatch([1 => $this->chain('FULL_PAID')]);

        self::assertSame(0, $run->shiftOpenings);
        self::assertSame([], $run->fiscalised);
    }

    public function testCancelledChainDoesNotOpenShift(): void
    {
        foreach (['CANCELLED', 'PARTIAL_CANCELLED'] as $status) {
            $run = $this->fiscaliseBatch([1 => $this->chain($status)]);

            self::assertSame(0, $run->shiftOpenings, $status);
            self::assertSame([], $run->fiscalised, $status);
        }
    }

    public function testUnknownChainStateDoesNotOpenShift(): void
    {
        $run = $this->fiscaliseBatch([1 => $this->chain(null)]);

        self::assertSame(0, $run->shiftOpenings);
        self::assertSame([], $run->fiscalised);
    }

    public function testPartialPaidChainStillOpensShift(): void
    {
        $run = $this->fiscaliseBatch([1 => $this->chain('PARTIAL_PAID')]);

        self::assertSame(1, $run->shiftOpenings);
        self::assertSame([[1, null]], $run->fiscalised);
    }

    public function testOrdinaryOrderStillOpensShift(): void
    {
        $run = $this->fiscaliseBatch([1 => []]);

        self::assertSame(1, $run->shiftOpenings);
        self::assertSame([[1, null]], $run->fiscalised);
    }

    public function testPlaceholderOrderOpensShiftAndReusesRow(): void
    {
        $run = $this->fiscaliseBatch([1 => [$this->row(40, '', FiscalReceiptsEntity::TYPE_SALE)]]);

        self::assertSame(1, $run->shiftOpenings);
        self::assertSame([[1, 40]], $run->fiscalised);
    }

    public function testMixedBatchOpensOnceAndSkipsChain(): void
    {
        $run = $this->fiscaliseBatch([1 => $this->chain('FULL_PAID'), 2 => []]);

        self::assertSame(1, $run->shiftOpenings);
        self::assertSame([[2, null]], $run->fiscalised);
    }

    public function testPaymentSkipOrderOpensNothing(): void
    {
        $run = $this->fiscaliseBatch([1 => []], [1 => self::SKIP_METHOD]);

        self::assertSame(0, $run->shiftOpenings);
        self::assertSame([], $run->fiscalised);
    }

    public function testPaymentSkipBeatsPartialPaidChain(): void
    {
        $run = $this->fiscaliseBatch([1 => $this->chain('PARTIAL_PAID')], [1 => self::SKIP_METHOD]);

        self::assertSame(0, $run->shiftOpenings);
        self::assertSame([], $run->fiscalised);
    }

    public function testUnreadableReceiptsTableOpensNothing(): void
    {
        $run = $this->fiscaliseBatch([1 => []], [], false);

        self::assertSame(0, $run->shiftOpenings);
        self::assertSame(['fiscalisation skipped: receipts table unreadable'], $run->logged);
    }

    /** @return object[] рядки ланцюжка; $status null — стан невідомий */
    private function chain(?string $status): array
    {
        $prepayment = $this->row(10, 'prepay-id', FiscalReceiptsEntity::TYPE_PREPAYMENT);
        $prepayment->relation_id = 'relation';
        $prepayment->chain_status = $status;

        return [$prepayment];
    }

    private function row(int $id, string $receiptId, string $type): object
    {
        return (object)[
            'id'              => $id,
            'order_id'        => 0,
            'receipt_id'      => $receiptId,
            'receipt_type'    => $type,
            'relation_id'     => null,
            'chain_status'    => null,
            'chain_paid_sum'  => null,
            'chain_total_sum' => null,
        ];
    }

    /**
     * @param array<int, object[]> $receiptsByOrder order_id => рядки чеків
     * @param array<int, int>      $methodByOrder   order_id => спосіб оплати
     */
    private function fiscaliseBatch(array $receiptsByOrder, array $methodByOrder = [], bool $tableReadable = true): object
    {
        $rows = [];
        foreach ($receiptsByOrder as $orderId => $orderRows) {
            foreach ($orderRows as $row) {
                $row->order_id = $orderId;
                $rows[] = $row;
            }
        }

        $orders = [];
        foreach (array_keys($receiptsByOrder) as $orderId) {
            $orders[$orderId] = (object)['id' => $orderId, 'payment_method_id' => $methodByOrder[$orderId] ?? 1];
        }

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn ($class) => [
            OrdersEntity::class => new class ($orders) {
                public function __construct(private array $rows) {}
                public function mappedBy($field) { return $this; }
                public function find(array $filter = []) { return $this->rows; }
            },
            PurchasesEntity::class => new class {
                public function count(array $filter = []) { return 1; }
            },
            PaymentsEntity::class => new class {
                public function mappedBy($field) { return $this; }
                public function find(array $filter = [])
                {
                    return [
                        1 => (object)[Init::PAYMENT_SKIP_FIELD => 0],
                        ShiftOpensOnlyForRealReceiptTest::skipMethod() => (object)[Init::PAYMENT_SKIP_FIELD => 1],
                    ];
                }
            },
            FiscalReceiptsEntity::class => new class ($rows) {
                private bool $newestFirst = false;
                public function __construct(private array $rows) {}
                public function order($order = null, array $additionalData = []) { $this->newestFirst = true; return $this; }
                public function find(array $filter = [])
                {
                    return array_values(array_filter($this->rows, fn ($r) => in_array($r->order_id, (array)$filter['order_id'], true)));
                }
                public function findOne(array $filter = [])
                {
                    foreach ($this->rows as $row) {
                        if ($row->order_id === $filter['order_id'] && $row->receipt_type === $filter['receipt_type']) {
                            return $row;
                        }
                    }
                    return null;
                }
            },
        ][$class] ?? null);

        $shifts = new class extends CheckboxShiftsHelper {
            public int $openings = 0;
            public function __construct() {}
            public function openShiftIfNeeded(int $maxWaitAttempts = 5, int $waitSeconds = 2): ?object
            {
                $this->openings++;
                return null;
            }
        };

        $helper = new class extends CheckboxReceiptsHelper {
            public array $fiscalised = [];
            public array $logged = [];
            public function __construct() {}
            public function fiscaliseOrder(int $orderId, ?int $emptyReceiptId = null)
            {
                $this->fiscalised[] = [$orderId, $emptyReceiptId];
                return null;
            }
            protected function logFailure(string $event, array $context = []): void
            {
                $this->logged[] = $event;
            }
        };

        Closure::bind(function () use ($factory, $shifts, $tableReadable) {
            $this->entityFactory = $factory;
            $this->shiftsHelper = $shifts;
            $this->receiptsTableReadable = $tableReadable;
        }, $helper, CheckboxReceiptsHelper::class)();

        $helper->createReceiptsForPaidOrders(array_keys($receiptsByOrder), 1);

        return (object)[
            'shiftOpenings' => $shifts->openings,
            'fiscalised'    => $helper->fiscalised,
            'logged'        => $helper->logged,
        ];
    }

    public static function skipMethod(): int
    {
        return self::SKIP_METHOD;
    }
}
