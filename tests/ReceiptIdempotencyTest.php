<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\BackendTranslations;
use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\OrdersEntity;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiOutcome;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPrepaymentHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptDelivery;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
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
                if (isset($f['pending_intent'])) {
                    return array_values(array_filter($this->rows, fn ($r) => (string) $r->receipt_id === '' && !empty($r->relation_id) && $r->receipt_type !== 'prepayment'));
                }
                if (isset($f['pending_placeholder'])) {
                    return array_values(array_filter($this->rows, fn ($r) => (string) $r->receipt_id === '' && empty($r->relation_id) && $r->receipt_type === 'sale'));
                }
                return array_values(array_filter($this->rows, fn ($r) => !isset($f['receipt_id']) || (string) $r->receipt_id === $f['receipt_id']));
            }
            public function findOne(array $f = [])
            {
                foreach (array_reverse($this->rows) as $r) {
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
            public function count(array $f = []) { return count(array_filter($this->rows, fn ($r) => $r->receipt_type === ($f['receipt_type'] ?? $r->receipt_type))); }
            public bool $failAdd = false;
            public function add($o) { if ($this->failAdd) { return false; } $o = (object) $o; $o->id = $this->nextId++; $o->receipt_id ??= ''; $this->rows[] = $o; return $o->id; }
            public function update($id, $o) { foreach ($this->rows as $r) { if ((int) $r->id === (int) $id) { foreach ((array) $o as $k => $v) { $r->$k = $v; } } } return $id; }
            public function delete($id) { $this->rows = array_values(array_filter($this->rows, fn ($r) => (int) $r->id !== (int) $id)); }
        };

        $orders = new class {
            public array $list = [];
            public ?object $order = null;
            public function find(array $f = []) { return $this->list; }
            public function get($id) { return $this->order; }
        };

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn ($c) => [FiscalReceiptsEntity::class => $entity, OrdersEntity::class => $orders][$c] ?? null);

        $translations = $this->createStub(BackendTranslations::class);
        $translations->method('getTranslation')->willReturnArgument(0);

        $shifts = new class extends CheckboxShiftsHelper {
            public int $reopened = 0;
            public bool $canReopen = true;
            public ?Closure $whenReopenFails = null;
            public function __construct() {}
            public function reopenAfterRemoteClose(): ?object
            {
                $this->reopened++;
                if ($this->canReopen) {
                    return (object) ['status' => 'OPENED'];
                }
                if ($this->whenReopenFails !== null) {
                    ($this->whenReopenFails)();
                }
                return null;
            }
            public function openShiftIfNeeded(int $maxWaitAttempts = 5, int $waitSeconds = 2): ?object { return (object) ['status' => 'OPENED']; }
            public function getCashierInfo() { return ['full_name' => 'Каса']; }
        };

        $helper = new class ($script, $lockFree) extends CheckboxReceiptsHelper {
            public array $requests = [];
            public array $payloads = [];
            public array $paidCalls = [];
            public int $locks = 0;
            public function __construct(public array $script, public bool $lockFree) {}
            protected function makeApiRequest(string $url, array $params = [], array $requestParams = [])
            {
                $this->requests[] = [$requestParams['method'] ?? 'GET', $url, $params['id'] ?? null];
                $this->payloads[] = $params;
                [$errors, $response] = array_shift($this->script);
                $this->errors = $errors;
                return $response;
            }
            public function call(string $url, array $params, string $method) { return $this->makeApiRequest($url, $params, ['method' => $method]); }
            protected function acquireOrderLock(int $orderId): bool { $this->locks++; return $this->lockFree; }
            protected function releaseOrderLock(int $orderId): void {}
            public function getAccessToken() { return []; }
            public function createReceiptsForPaidOrders(array $ids, int $status) { $this->paidCalls[] = $ids; return null; }
            protected function logFailure(string $event, array $context = []): void {}
        };

        $prepayment = new class ($helper) extends CheckboxPrepaymentHelper {
            public function __construct(private object $api) {}
            public function createChain(array $payload) { return $this->api->call('prepayment-receipts', $payload, 'POST'); }
            public function addPayment(string $relationId, array $payload) { return $this->api->call("prepayment-receipts/$relationId", $payload, 'POST'); }
            public function chainStatus(string $relationId): ?array
            {
                $response = $this->api->call("prepayment-receipts/$relationId", [], 'GET');
                return is_array($response) && isset($response['pre_payment_status']) ? $response : null;
            }
            public function returnChain(string $relationId, string $cashierName, ?array $delivery = null) { return $this->api->call("prepayment-receipts/$relationId/return", array_filter(['delivery' => $delivery]), 'POST'); }
        };

        $settingValues = new \ArrayObject();
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(fn ($name) => $settingValues[$name] ?? null);

        Closure::bind(function () use ($factory, $translations, $shifts, $prepayment, $settings) {
            $this->entityFactory = $factory;
            $this->translations = $translations;
            $this->settings = $settings;
            $this->accessToken = 'token';
            $this->shiftsHelper = $shifts;
            $this->prepaymentHelper = $prepayment;
            $this->receiptsTableReadable = true;
        }, $helper, CheckboxReceiptsHelper::class)();

        return (object) ['helper' => $helper, 'entity' => $entity, 'shifts' => $shifts, 'orders' => $orders, 'settings' => $settingValues];
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

        self::assertSame('uuid-1', $env->entity->rows[0]->relation_id, '404 не привід міняти id: повтор із тим самим id безпечний');
        self::assertSame([[22991]], $env->helper->paidCalls);
    }

    /** Без зв'язку з продажем той самий продаж лишається непокритим, і другий клік дає друге повернення. */
    public function testSettledReturnKeepsItsLinkToTheSale(): void
    {
        $env = $this->scripted([$this->timeout()], [$this->row(1, 'sale-1')]);
        $this->send($env, true);
        $intent = $env->entity->rows[1];
        self::assertSame('sale-1', $intent->related_receipt_id, 'зв\'язок пишеться вже в намір');

        $env->helper->script = [$this->done('ret-1')];
        $env->helper->reconcilePendingReceipts();

        self::assertSame('ret-1', $intent->receipt_id);
        self::assertSame('sale-1', $intent->related_receipt_id);
        self::assertSame('sviat__checkbox__errors_nothing_to_return', $this->send($env, true)->message);
    }

    public function testSettlingOldIntentWithoutLinkRestoresIt(): void
    {
        $env = $this->scripted([$this->done('ret-1')], [$this->row(1, 'sale-1'), $this->row(2, '', 'return', 'uuid-r')]);

        $env->helper->reconcilePendingReceipts();

        self::assertSame('sale-1', $env->entity->rows[1]->related_receipt_id);
    }

    /** Відмова на повторний id не доводить, що перша спроба не створила чек. */
    #[DataProvider('failuresOnRetry')]
    public function testFailedRetryKeepsUnresolvedId(array $failure): void
    {
        $env = $this->scripted([$failure], [$this->row(7, '', 'sale', 'uuid-1')]);
        $env->shifts->canReopen = false;

        $this->send($env, false, 7);

        self::assertSame('uuid-1', $env->entity->rows[0]->relation_id);
    }

    public static function failuresOnRetry(): array
    {
        return [
            'rejected'     => [[['http_code' => 422, 'message' => 'x', 'response' => []], []]],
            'shift closed' => [[['http_code' => 400, 'message' => 'x', 'response' => ['code' => 'shift.not_opened']], []]],
        ];
    }

    public function testBusyOrderIsNotSettled(): void
    {
        $env = $this->scripted([$this->done('x')], [$this->row(7, '', 'sale', 'uuid-1')], false);

        $env->helper->reconcilePendingReceipts();

        self::assertSame([], $env->helper->requests, 'паралельний запит ще може створювати цей чек');
    }

    public function testSaleWaitsWhileChainHoldsOrder(): void
    {
        $chain = $this->row(3, 'prep', 'prepayment', 'chain-1');
        $chain->chain_status = 'PARTIAL_PAID';
        $chain->chain_paid_sum = 100;
        $chain->chain_total_sum = 200;
        $env = $this->scripted([], [$chain]);

        self::assertSame('sviat__checkbox__errors_chain_is_open', $this->send($env)->message);
        self::assertSame([], $env->helper->requests);
    }

    public function testIntentThatWasNotSavedStopsTheRequest(): void
    {
        $env = $this->scripted([$this->done()]);
        $env->entity->failAdd = true;

        $this->send($env);

        self::assertSame([], $env->helper->requests);
    }

    public function testReceiptWithErrorStatusIsNotCompleted(): void
    {
        $env = $this->scripted([[[], ['id' => 'x', 'status' => 'ERROR']]], [$this->row(7, '', 'sale', 'uuid-1')]);

        $env->helper->reconcilePendingReceipts();

        self::assertSame('', $env->entity->rows[0]->receipt_id);
    }

    public function testCardKeepsActionsWhileReceiptIsUnconfirmed(): void
    {
        $template = (string) file_get_contents(__DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox/Backend/design/html/fiscal_receipt_order_block.tpl');

        self::assertStringNotContainsString('{elseif $emptyOrderReceiptsCount}', $template, 'повтор безпечний, тож дію ховати нема чого');
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

    private function chainRow(): object
    {
        $row = $this->row(1, 'advance', 'prepayment', 'CHAIN-1');
        $row->chain_status = 'PARTIAL_PAID';
        $row->chain_paid_sum = 10000;
        $row->chain_total_sum = 30000;

        return $row;
    }

    private function chainIsPartlyPaid(): array
    {
        return [[], ['pre_payment_status' => 'PARTIAL_PAID', 'paid_sum' => 10000, 'total_sum' => 30000]];
    }

    private function rejected(): array { return [['http_code' => 422, 'message' => 'x', 'response' => ['code' => 'validation']], []]; }

    private function callPrivate(object $env, string $name, array $args)
    {
        return new ReflectionMethod(CheckboxReceiptsHelper::class, $name)->invoke($env->helper, ...$args);
    }

    private function openChain(object $env)
    {
        $context = ['cashierName' => 'Каса', 'goods' => [], 'footer' => '', 'delivery' => null, 'goodsTotal' => 30000];

        return $this->callPrivate($env, 'openChain', [22991, 10000, 'cash', $context]);
    }

    private function closeChain(object $env)
    {
        return $this->callPrivate($env, 'closeChain', [22991, null, 'cash', null]);
    }

    private function failReopen(object $env, array $errorsLeftByReopen): void
    {
        $env->shifts->canReopen = false;
        $env->shifts->whenReopenFails = function () use ($env, $errorsLeftByReopen) {
            $env->helper->errors = $errorsLeftByReopen;
        };
    }

    public static function failedReopens(): array
    {
        return [
            'opening timed out'         => [['message' => 'timed out']],
            'shift never became active' => [[]],
        ];
    }

    /** Відмова «зміну закрито» доводить, що документа немає, хоч би що лишив reopen. */
    #[DataProvider('failedReopens')]
    public function testFailedReopenDropsChainIntentInsteadOfFreezingOrder(array $errorsLeftByReopen): void
    {
        $env = $this->scripted([$this->closed()]);
        $this->failReopen($env, $errorsLeftByReopen);

        $result = $this->openChain($env);

        self::assertIsObject($result);
        self::assertSame([], $env->entity->rows, 'ланцюжка немає, намір не сміє блокувати замовлення');
    }

    #[DataProvider('failedReopens')]
    public function testFailedReopenDoesNotCancelLiveChain(array $errorsLeftByReopen): void
    {
        $env = $this->scripted([$this->closed()], [$this->chainRow()]);
        $this->failReopen($env, $errorsLeftByReopen);

        $result = $this->callPrivate($env, 'returnChainReceipts', [22991]);

        self::assertIsObject($result);
        self::assertSame('PARTIAL_PAID', $env->entity->rows[0]->chain_status);
    }

    #[DataProvider('failedReopens')]
    public function testFailedReopenDoesNotSaveRefusalAsAfterPayment(array $errorsLeftByReopen): void
    {
        $env = $this->scripted([$this->chainIsPartlyPaid(), $this->closed()], [$this->chainRow()]);
        $this->failReopen($env, $errorsLeftByReopen);

        $result = $this->closeChain($env);

        self::assertIsObject($result);
        self::assertCount(1, $env->entity->rows, 'свіжий намір знято: чека точно немає');
        self::assertSame('advance', $env->entity->rows[0]->receipt_id);
    }

    public static function chainRefusals(): array
    {
        return [
            'shift closed' => ['closed'],
            'validation'   => ['rejected'],
        ];
    }

    /** Перевірка зміни йде раніше за перевірку тіла, тож відмова не доводить, що першого POST не було. */
    #[DataProvider('chainRefusals')]
    public function testRefusalKeepsAfterPaymentIntentWithReusedId(string $refusal): void
    {
        $env = $this->scripted([$this->chainIsPartlyPaid(), $this->$refusal()], [$this->chainRow(), $this->row(2, '', 'after_payment', 'earlier-uuid')]);
        $env->shifts->canReopen = false;

        $this->closeChain($env);

        self::assertCount(2, $env->entity->rows);
        self::assertSame('earlier-uuid', $env->entity->rows[1]->relation_id);
    }

    #[DataProvider('chainRefusals')]
    public function testRefusalDropsFreshAfterPaymentIntent(string $refusal): void
    {
        $env = $this->scripted([$this->chainIsPartlyPaid(), $this->$refusal()], [$this->chainRow()]);
        $env->shifts->canReopen = false;

        $this->closeChain($env);

        self::assertCount(1, $env->entity->rows);
    }

    private function buyer(object $env, int $mode): void
    {
        $env->settings['sviat__checkbox__send_message'] = $mode;
        $env->orders->order = (object) ['id' => 22991, 'email' => '', 'phone' => '+380671234567'];
    }

    /** Чек авансу й так ішов покупцеві; остаточний чек ланцюжка — теж його чек. */
    public function testAfterPaymentReceiptGoesToTheBuyer(): void
    {
        $env = $this->scripted([$this->chainIsPartlyPaid(), $this->done('final'), [[], ['pre_payment_status' => 'FULL_PAID']]], [$this->chainRow()]);
        $this->buyer($env, CheckboxReceiptDelivery::EMAIL_ELSE_PHONE);

        $this->closeChain($env);

        self::assertSame(['POST', 'prepayment-receipts/CHAIN-1'], array_slice($env->helper->requests[1], 0, 2));
        self::assertSame(['phone' => '380671234567'], $env->helper->payloads[1]['delivery'] ?? null);
    }

    public function testAfterPaymentHonoursDeliveryOff(): void
    {
        $env = $this->scripted([$this->chainIsPartlyPaid(), $this->done('final'), [[], ['pre_payment_status' => 'FULL_PAID']]], [$this->chainRow()]);
        $this->buyer($env, CheckboxReceiptDelivery::NONE);

        $this->closeChain($env);

        self::assertArrayNotHasKey('delivery', $env->helper->payloads[1]);
    }

    public function testChainReturnReceiptsGoToTheBuyer(): void
    {
        $returned = [[], [['id' => 'ret-1', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01']]];
        $env = $this->scripted([$returned], [$this->chainRow()]);
        $this->buyer($env, CheckboxReceiptDelivery::PHONE);

        $this->callPrivate($env, 'returnChainReceipts', [22991]);

        self::assertSame(['phone' => '380671234567'], $env->helper->payloads[0]['delivery'] ?? null);
    }

    public function testAdvanceReceiptCarriesDeliveryFromContext(): void
    {
        $env = $this->scripted([$this->done('advance')]);
        $context = ['cashierName' => 'Каса', 'goods' => [], 'footer' => '', 'delivery' => ['emails' => ['buyer@example.com']], 'goodsTotal' => 30000];

        $this->callPrivate($env, 'openChain', [22991, 10000, 'cash', $context]);

        self::assertSame(['emails' => ['buyer@example.com']], $env->helper->payloads[0]['delivery'] ?? null);
    }

    private function failedDocument(array $extra = []): array
    {
        return [[], ['id' => 'earlier-uuid', 'status' => 'ERROR', 'created_at' => '2026-01-01', 'updated_at' => '2026-01-01'] + $extra];
    }

    public function testErroredDocumentIsNotSavedAsIssuedAndFreesTheId(): void
    {
        $env = $this->scripted([$this->exists(), $this->failedDocument(), $this->done('second')], [$this->row(5, '', 'sale', 'earlier-uuid')]);

        $result = $this->send($env, false, 5);

        self::assertIsObject($result);
        self::assertSame('', $env->entity->rows[0]->receipt_id);
        self::assertNull($env->entity->rows[0]->relation_id, 'той самий id давав би той самий зіпсований документ');

        $this->send($env, false, 5);

        self::assertSame('second', $env->entity->rows[0]->receipt_id);
        self::assertNotSame('earlier-uuid', $env->helper->requests[2][2]);
    }

    public function testErroredDocumentWithFiscalCodeKeepsTheId(): void
    {
        $env = $this->scripted([$this->exists(), $this->failedDocument(['fiscal_code' => 'AB123'])], [$this->row(5, '', 'sale', 'earlier-uuid')]);

        $this->send($env, false, 5);

        self::assertSame('', $env->entity->rows[0]->receipt_id);
        self::assertSame('earlier-uuid', $env->entity->rows[0]->relation_id);
    }

    private function lastReceiptShownFor(array $rows): ?object
    {
        $env = $this->scripted([], $rows);
        $extender = new ReflectionClass(BackendExtender::class)->newInstanceWithoutConstructor();
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($env->entity);
        Closure::bind(fn () => $this->entityFactory = $factory, $extender, BackendExtender::class)();

        $order = (object) ['id' => 22991];
        $extender->findOrders([$order]);

        return $order->receipt;
    }

    public function testUnfinishedRowIsNotShownAsIssuedInOrderList(): void
    {
        self::assertNull($this->lastReceiptShownFor([$this->row(5, '', 'sale', 'uuid')]));
    }

    public function testUnfinishedReturnDoesNotHideIssuedSaleInOrderList(): void
    {
        $shown = $this->lastReceiptShownFor([$this->row(5, 'real'), $this->row(6, '', 'return', 'uuid')]);

        self::assertSame('real', $shown->receipt_id);
    }
}
