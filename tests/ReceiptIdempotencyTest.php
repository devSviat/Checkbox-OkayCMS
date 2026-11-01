<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\BackendTranslations;
use Okay\Core\EntityFactory;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiOutcome;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

/**
 * Один чек на одну дію, що б не сталося з мережею: відповідь може не дійти, а каса
 * може закрити зміну сама. Коди відповідей перевірено на тестовому касирі.
 */
class ReceiptIdempotencyTest extends TestCase
{
    #[DataProvider('outcomes')]
    public function testResponsesAreClassified(array $errors, $response, string $expected): void
    {
        self::assertSame($expected, CheckboxApiOutcome::of($errors, $response));
    }

    public static function outcomes(): array
    {
        $api = fn (int $http, string $code = '') => ['http_code' => $http, 'message' => 'x', 'response' => ['code' => $code]];

        return [
            'receipt created'  => [[], ['id' => 'abc'], CheckboxApiOutcome::SUCCESS],
            'empty body'       => [[], false, CheckboxApiOutcome::UNKNOWN],
            'text body'        => [[], '<html>', CheckboxApiOutcome::UNKNOWN],
            'curl timeout'     => [['message' => 'Operation timed out'], false, CheckboxApiOutcome::UNKNOWN],
            'server error'     => [$api(502), [], CheckboxApiOutcome::UNKNOWN],
            'rate limit'       => [$api(429), [], CheckboxApiOutcome::UNKNOWN],
            'validation'       => [$api(422), [], CheckboxApiOutcome::REJECTED],
            'shift closed'     => [$api(400, 'shift.not_opened'), [], CheckboxApiOutcome::SHIFT_CLOSED],
            'duplicate id'     => [$api(400, 'receipt.already_exists'), [], CheckboxApiOutcome::ALREADY_EXISTS],
        ];
    }

    /** @param list<array{0: array, 1: mixed}> $script відповіді API по черзі: [errors, response] */
    private function scripted(array $script, array $rows = [], bool $lockFree = true): object
    {
        $entity = new class ($rows) {
            public int $nextId = 100;
            public function __construct(public array $rows) {}
            public function order($o = null, array $a = []) { return $this; }
            public function find(array $f = [])
            {
                return array_values(array_filter($this->rows, fn ($r) => !isset($f['receipt_id']) || (string) $r->receipt_id === $f['receipt_id']));
            }
            public function findOne(array $f = [])
            {
                foreach ($this->rows as $r) {
                    $ok = true;
                    foreach ($f as $k => $v) {
                        if (in_array($k, ['limit', 'page'], true)) {
                            continue;
                        }
                        $ok = $ok && (string) ($r->$k ?? '') === (string) $v;
                    }
                    if ($ok) {
                        return $r;
                    }
                }
                return null;
            }
            public function add($o) { $o = (object) $o; $o->id = $this->nextId++; $o->receipt_id ??= ''; $this->rows[] = $o; return $o->id; }
            public function update($id, $o) { foreach ($this->rows as $r) { if ((int) $r->id === (int) $id) { foreach ((array) $o as $k => $v) { $r->$k = $v; } } } return $id; }
            public function delete($id) { $this->rows = array_values(array_filter($this->rows, fn ($r) => (int) $r->id !== (int) $id)); }
        };

        $orders = new class {
            public array $list = [];
            public function find(array $f = []) { return $this->list; }
        };

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn ($c) => [FiscalReceiptsEntity::class => $entity, OrdersEntity::class => $orders][$c] ?? null);

        $translations = $this->createStub(BackendTranslations::class);
        $translations->method('getTranslation')->willReturnArgument(0);

        $shifts = new class extends CheckboxShiftsHelper {
            public int $reopened = 0;
            public bool $canReopen = true;
            public function __construct() {}
            public function reopenAfterRemoteClose(): ?object
            {
                $this->reopened++;
                return $this->canReopen ? (object) ['status' => 'OPENED'] : null;
            }
        };

        $helper = new class ($script, $lockFree) extends CheckboxReceiptsHelper {
            public array $requests = [];
            public array $paidCalls = [];
            public int $locks = 0;
            public function __construct(public array $script, public bool $lockFree) {}
            protected function makeApiRequest(string $url, array $params = [], array $requestParams = [])
            {
                $this->requests[] = [$requestParams['method'] ?? 'GET', $url, $params['id'] ?? null];
                [$errors, $response] = array_shift($this->script);
                $this->errors = $errors;
                return $response;
            }
            protected function acquireOrderLock(int $orderId): bool { $this->locks++; return $this->lockFree; }
            protected function releaseOrderLock(int $orderId): void {}
            public function getAccessToken() { return []; }
            public function createReceiptsForPaidOrders(array $ids, int $status) { $this->paidCalls[] = $ids; return null; }
            protected function logFailure(string $event, array $context = []): void {}
        };

        Closure::bind(function () use ($factory, $translations, $shifts) {
            $this->entityFactory = $factory;
            $this->translations = $translations;
            $this->accessToken = 'token';
            $this->shiftsHelper = $shifts;
            $this->receiptsTableReadable = true;
        }, $helper, CheckboxReceiptsHelper::class)();

        return (object) ['helper' => $helper, 'entity' => $entity, 'shifts' => $shifts, 'orders' => $orders];
    }

    private function send(object $env, bool $isReturn = false, ?int $rowId = null)
    {
        $method = new ReflectionMethod(CheckboxReceiptsHelper::class, 'underOrderLock');
        $send = new ReflectionMethod(CheckboxReceiptsHelper::class, 'sendReceipt');

        return $method->invoke($env->helper, 22991, fn () => $send->invoke($env->helper, 22991, $isReturn, $rowId, ['goods' => []]));
    }

    private function row(int $id, string $receiptId, string $type = 'sale', ?string $uuid = null): object
    {
        return (object) ['id' => $id, 'order_id' => 22991, 'receipt_id' => $receiptId, 'receipt_type' => $type, 'relation_id' => $uuid, 'is_return' => (int) ($type === 'return'), 'related_receipt_id' => null];
    }

    private function closed(): array { return [['http_code' => 400, 'message' => 'x', 'response' => ['code' => 'shift.not_opened']], []]; }
    private function exists(): array { return [['http_code' => 400, 'message' => 'x', 'response' => ['code' => 'receipt.already_exists']], []]; }
    private function done(string $id = 'real'): array { return [[], ['id' => $id, 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']]; }
    private function timeout(): array { return [['message' => 'timed out'], false]; }

    public function testIntentIsWrittenBeforeTheRequestAndCompletedAfter(): void
    {
        $env = $this->scripted([$this->done()]);

        $this->send($env);

        self::assertCount(1, $env->entity->rows);
        self::assertSame('real', $env->entity->rows[0]->receipt_id);
        self::assertSame($env->helper->requests[0][2], $env->entity->rows[0]->relation_id, 'у запиті той самий id, що в рядку');
    }

    public function testLostResponseKeepsIntentAndRetryReusesId(): void
    {
        $env = $this->scripted([$this->timeout()]);

        $this->send($env);
        $firstId = $env->helper->requests[0][2];

        self::assertSame($firstId, $env->entity->rows[0]->relation_id, 'намір лишається');
        self::assertSame('', (string) $env->entity->rows[0]->receipt_id);

        $env->helper->script = [$this->exists(), $this->done()];
        $this->send($env);

        self::assertSame($firstId, $env->helper->requests[1][2], 'новий id дав би другий чек');
        self::assertSame(['GET', 'receipts/' . $firstId, null], $env->helper->requests[2]);
        self::assertSame('real', $env->entity->rows[0]->receipt_id);
        self::assertCount(1, $env->entity->rows);
    }

    public function testRejectionRemovesIntentSoRetryIsFresh(): void
    {
        $env = $this->scripted([[['http_code' => 422, 'message' => 'x', 'response' => []], ['message' => 'x']]]);

        $this->send($env);

        self::assertSame([], $env->entity->rows);
    }

    public function testRemotelyClosedShiftIsReopenedAndReceiptSent(): void
    {
        $env = $this->scripted([$this->closed(), $this->done()]);

        $this->send($env);

        self::assertSame(1, $env->shifts->reopened);
        self::assertSame($env->helper->requests[0][2], $env->helper->requests[1][2]);
        self::assertSame('real', $env->entity->rows[0]->receipt_id);
    }

    public function testShiftThatCannotReopenLeavesPlaceholderForCron(): void
    {
        $env = $this->scripted([$this->closed()]);
        $env->shifts->canReopen = false;

        $this->send($env);

        self::assertCount(1, $env->entity->rows, 'чек не загублено');
        self::assertNull($env->entity->rows[0]->relation_id);
    }

    public function testSecondSaleUnderLockIsRefused(): void
    {
        $env = $this->scripted([], [$this->row(1, 'already')]);

        $result = $this->send($env);

        self::assertSame('sviat__checkbox__errors_sale_exists', $result->message);
        self::assertSame([], $env->helper->requests);
    }

    public function testBusyOrderSendsNothing(): void
    {
        $env = $this->scripted([$this->done()], [], false);

        $result = $this->send($env);

        self::assertSame('sviat__checkbox__errors_order_busy', $result->message);
        self::assertSame([], $env->helper->requests);
    }

    public function testNestedActionReusesHeldLock(): void
    {
        $env = $this->scripted([]);
        $lock = new ReflectionMethod(CheckboxReceiptsHelper::class, 'underOrderLock');

        $result = $lock->invoke($env->helper, 1, fn () => $lock->invoke($env->helper, 1, fn () => 'done'));

        self::assertSame('done', $result);
        self::assertSame(1, $env->helper->locks);
    }

    public function testReturnWithoutUncoveredSaleIsRefused(): void
    {
        $env = $this->scripted([], [$this->row(1, 'sale-1'), (object) ['id' => 2, 'order_id' => 22991, 'receipt_id' => 'ret-1', 'receipt_type' => 'return', 'relation_id' => null, 'is_return' => 1, 'related_receipt_id' => 'sale-1']]);

        $result = $this->send($env, true);

        self::assertSame('sviat__checkbox__errors_nothing_to_return', $result->message);
        self::assertSame([], $env->helper->requests);
    }

    public function testReconcileFinishesReceiptThatActuallyExists(): void
    {
        $env = $this->scripted([$this->done('found')], [$this->row(7, '', 'sale', 'uuid-1')]);

        $env->helper->reconcilePendingReceipts();

        self::assertSame(['GET', 'receipts/uuid-1', null], $env->helper->requests[0]);
        self::assertSame('found', $env->entity->rows[0]->receipt_id);
        self::assertSame([], $env->helper->paidCalls);
    }

    public function testRecoveredTestReceiptKeepsItsTestMark(): void
    {
        $env = $this->scripted([[[], ['id' => 'found', 'fiscal_code' => 'TEST-abc']]], [$this->row(7, '', 'sale', 'uuid-1')]);

        $env->helper->reconcilePendingReceipts();

        self::assertSame(1, $env->entity->rows[0]->is_test);
    }

    public function testReconcileRetriesFreshlyPaidOrderWhenReceiptIsMissing(): void
    {
        $env = $this->scripted([[['http_code' => 404, 'message' => 'x', 'response' => []], []]], [$this->row(7, '', 'sale', 'uuid-1')]);
        $env->orders->list = [(object) ['id' => 22991, 'paid' => 1, 'payment_date' => date('Y-m-d H:i:s')]];

        $env->helper->reconcilePendingReceipts();

        self::assertNull($env->entity->rows[0]->relation_id);
        self::assertSame([[22991]], $env->helper->paidCalls);
    }

    public function testReconcileLeavesOldOrdersAlone(): void
    {
        $env = $this->scripted([], [$this->row(7, '')]);
        $env->orders->list = [(object) ['id' => 22991, 'paid' => 1, 'payment_date' => '2020-01-01 00:00:00']];

        $env->helper->reconcilePendingReceipts();

        self::assertSame([], $env->helper->paidCalls);
    }

    public function testUnreachableApiKeepsIntentUntouched(): void
    {
        $env = $this->scripted([$this->timeout()], [$this->row(7, '', 'sale', 'uuid-1')]);
        $env->orders->list = [(object) ['id' => 22991, 'paid' => 1, 'payment_date' => date('Y-m-d H:i:s')]];

        $env->helper->reconcilePendingReceipts();

        self::assertSame('uuid-1', $env->entity->rows[0]->relation_id);
        self::assertSame([], $env->helper->paidCalls, 'без відповіді не можна виставляти новий чек');
    }
}
