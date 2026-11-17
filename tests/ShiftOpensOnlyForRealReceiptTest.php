<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Entities\PurchasesEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxChainDecision;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPrepaymentHelper;
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

    /**
     * Збережений стан застаріває: аванс могли закрити чи повернути з кабінету
     * Checkbox. Зміну відкриваємо лише під залишок, який Checkbox підтвердив.
     */
    public function testChainThatCheckboxDoesNotFindOpensNoShiftAndStops(): void
    {
        $chain = $this->chain('PARTIAL_PAID');
        $run = $this->fiscaliseBatch([1 => $chain], [], true, [1 => ['pre_payment_status' => CheckboxChainDecision::STATUS_NOT_FOUND]]);

        self::assertSame(0, $run->shiftOpenings);
        self::assertSame([], $run->fiscalised);
        self::assertSame(1, $run->statusRequests);
        self::assertSame(CheckboxChainDecision::STATUS_NOT_FOUND, $chain[0]->chain_status);
        self::assertSame(['chain not found in Checkbox'], $run->warned);

        // Наступний тік: стан уже збережений, у мережу не йдемо і нічого не пишемо.
        $next = $this->fiscaliseBatch([1 => $chain]);

        self::assertSame(0, $next->shiftOpenings);
        self::assertSame(0, $next->statusRequests);
        self::assertSame([], $next->warned);
    }

    /** Checkbox не відповів: пропускаємо тік без зміни, рівно один запит стану. */
    public function testSilentCheckboxOpensNoShiftAndAsksOnce(): void
    {
        $chain = $this->chain('PARTIAL_PAID');
        $run = $this->fiscaliseBatch([1 => $chain], [], true, [1 => null]);

        self::assertSame(0, $run->shiftOpenings);
        self::assertSame([], $run->fiscalised);
        self::assertSame(1, $run->statusRequests);
        self::assertSame('PARTIAL_PAID', $chain[0]->chain_status);
    }

    public function testChainClosedOutsideOpensNoShift(): void
    {
        $chain = $this->chain('PARTIAL_PAID');
        $run = $this->fiscaliseBatch([1 => $chain], [], true, [1 => ['pre_payment_status' => 'FULL_PAID', 'paid_sum' => 100, 'total_sum' => 100]]);

        self::assertSame(0, $run->shiftOpenings);
        self::assertSame('FULL_PAID', $chain[0]->chain_status);
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
     * @param array<int, ?array>   $liveByOrder     order_id => відповідь GET стану; без запису — те, що в базі
     */
    private function fiscaliseBatch(array $receiptsByOrder, array $methodByOrder = [], bool $tableReadable = true, array $liveByOrder = []): object
    {
        $rows = [];
        foreach ($receiptsByOrder as $orderId => $orderRows) {
            foreach ($orderRows as $row) {
                $row->order_id = $orderId;
                if ($row->receipt_type === FiscalReceiptsEntity::TYPE_PREPAYMENT) {
                    $row->relation_id = 'relation-' . $orderId;
                }
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
                public function update($id, $state)
                {
                    foreach ($this->rows as $row) {
                        if ($row->id === $id) {
                            foreach ((array)$state as $field => $value) {
                                $row->$field = $value;
                            }
                        }
                    }
                    return $id;
                }
            },
        ][$class] ?? null);

        $live = [];
        foreach ($rows as $row) {
            if ($row->receipt_type === FiscalReceiptsEntity::TYPE_PREPAYMENT) {
                $live[$row->relation_id] = array_key_exists($row->order_id, $liveByOrder)
                    ? $liveByOrder[$row->order_id]
                    : ['pre_payment_status' => $row->chain_status];
            }
        }
        $prepayment = new class ($live) extends CheckboxPrepaymentHelper {
            public int $requests = 0;
            public function __construct(private array $live) {}
            public function chainStatus(string $relationId): ?array
            {
                $this->requests++;
                return $this->live[$relationId] ?? null;
            }
        };

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
            public array $warned = [];
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
            protected function logWarning(string $event, array $context = []): void
            {
                $this->warned[] = $event;
            }
        };

        Closure::bind(function () use ($factory, $shifts, $tableReadable, $prepayment) {
            $this->entityFactory = $factory;
            $this->shiftsHelper = $shifts;
            $this->prepaymentHelper = $prepayment;
            $this->receiptsTableReadable = $tableReadable;
        }, $helper, CheckboxReceiptsHelper::class)();

        $helper->createReceiptsForPaidOrders(array_keys($receiptsByOrder), 1);

        return (object)[
            'shiftOpenings' => $shifts->openings,
            'fiscalised'    => $helper->fiscalised,
            'logged'        => $helper->logged,
            'warned'        => $helper->warned,
            'statusRequests' => $prepayment->requests,
        ];
    }

    public static function skipMethod(): int
    {
        return self::SKIP_METHOD;
    }
}
