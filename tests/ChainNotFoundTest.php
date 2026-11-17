<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\BackendTranslations;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxChainDecision;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPrepaymentHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

/**
 * «Checkbox не знає такого ланцюжка» і «Checkbox не відповів» — різні стани.
 *
 * Перше не минає само: крон, який щоразу питав би заново, писав би дві помилки
 * кожні десять хвилин і відкривав би порожню зміну. Друге минає, і стан у базі
 * тоді чіпати не можна.
 */
class ChainNotFoundTest extends TestCase
{
    public function testMissingRelationIsReportedAsNotFound(): void
    {
        $logger = $this->logger();
        $helper = $this->prepayment($logger, $this->missing());

        $status = $helper->chainStatus('relation-1');

        self::assertSame(['pre_payment_status' => CheckboxChainDecision::STATUS_NOT_FOUND], $status);
        self::assertSame([], $logger->records, 'відсутній ланцюжок — не збій звʼязку, ERROR тут зайвий');
    }

    /** 404 на вході касира — це не відповідь про ланцюжок: запиту стану не було. */
    public function testNotFoundFromSigninIsNotAChainState(): void
    {
        $helper = $this->prepayment(null, $this->missing());
        Closure::bind(function () {
            $this->accessToken = null;
            $this->cashierLogin = 'login';
            $this->cashierPassword = 'password';
            $this->licenseKey = 'key';
        }, $helper, CheckboxApiHelper::class)();

        self::assertNull($helper->chainStatus('relation-1'));
    }

    public function testNotFoundFromSigninPersistsNothing(): void
    {
        $env = $this->receipts([$this->missing()]);
        Closure::bind(function () {
            $this->accessToken = null;
            $this->cashierLogin = 'login';
            $this->cashierPassword = 'password';
            $this->licenseKey = 'key';
        }, $env->prepayment, CheckboxApiHelper::class)();

        $chain = $env->helper->refreshOrderChainStatus(7);

        self::assertSame('PARTIAL_PAID', $chain['pre_payment_status']);
        self::assertSame('PARTIAL_PAID', $env->row->chain_status);
        self::assertNotContains('warning', array_column($env->logger->records, 'level'));
    }

    /** Загальний 404 FastAPI на невідомий маршрут не каже нічого про сам ланцюжок. */
    public function testRouteMissStaysUnknown(): void
    {
        $logger = $this->logger();
        $helper = $this->prepayment($logger, [
            ['message' => 'HTTP Error 404', 'http_code' => 404, 'response' => ['detail' => 'Not Found']],
            ['detail' => 'Not Found'],
        ]);

        self::assertNull($helper->chainStatus('relation-1'));
        self::assertSame(['error'], array_column($logger->records, 'level'));
    }

    /** Ручна післяплата на ланцюжку, якого Checkbox не знає, — порада, а не «ланцюжок закрито». */
    public function testManualAfterPaymentOnMissingChainGivesAdvice(): void
    {
        $env = $this->receipts([$this->missing()]);

        $result = $env->helper->createAfterPaymentReceipt(7, null, 'cash');

        self::assertSame('sviat__checkbox__chain_next_not_found', $result->message);
        self::assertSame(CheckboxChainDecision::STATUS_NOT_FOUND, $env->row->chain_status);
    }

    /** @return array<string, array{0: array, 1: mixed}> */
    public static function noAnswer(): array
    {
        return [
            'таймаут' => [['message' => 'Operation timed out'], false],
            '5xx'     => [['message' => 'HTTP Error 502', 'http_code' => 502, 'response' => []], []],
            '403'     => [['message' => 'Forbidden', 'http_code' => 403, 'response' => ['message' => 'Forbidden']], ['message' => 'Forbidden']],
        ];
    }

    #[DataProvider('noAnswer')]
    public function testOtherFailuresStayUnknown(array $errors, $response): void
    {
        $logger = $this->logger();
        $helper = $this->prepayment($logger, [$errors, $response]);

        self::assertNull($helper->chainStatus('relation-1'));
        self::assertCount(1, $logger->records);
        self::assertSame('error', $logger->records[0]['level']);
        self::assertSame('Sviat/Checkbox: chain status unavailable', $logger->records[0]['message']);
    }

    public function testRefreshPersistsNotFoundAndWarnsOnce(): void
    {
        $env = $this->receipts([$this->missing()]);

        $chain = $env->helper->refreshOrderChainStatus(7);

        self::assertSame(CheckboxChainDecision::STATUS_NOT_FOUND, $chain['pre_payment_status']);
        self::assertSame(CheckboxChainDecision::STATUS_NOT_FOUND, $env->row->chain_status);
        self::assertSame(['warning'], array_column($env->logger->records, 'level'));
        self::assertSame(7, $env->logger->records[0]['context']['order_id']);
    }

    /** Кнопка «Оновити стан» мусить уміти зняти not_found, коли Checkbox знову бачить ланцюжок. */
    public function testLaterAnswerOverwritesNotFound(): void
    {
        $env = $this->receipts([
            $this->missing(),
            [[], ['pre_payment_status' => 'PARTIAL_PAID', 'paid_sum' => 30000, 'total_sum' => 100000]],
        ]);

        $env->helper->refreshOrderChainStatus(7);
        $chain = $env->helper->refreshOrderChainStatus(7);

        self::assertSame('PARTIAL_PAID', $chain['pre_payment_status']);
        self::assertSame(70000, $chain['left_to_pay']);
    }

    public function testNoAnswerKeepsStoredState(): void
    {
        $env = $this->receipts([[['message' => 'Operation timed out'], false]]);

        $chain = $env->helper->refreshOrderChainStatus(7);

        self::assertSame('PARTIAL_PAID', $chain['pre_payment_status']);
        self::assertSame('PARTIAL_PAID', $env->row->chain_status);
        self::assertSame(['error'], array_column($env->logger->records, 'level'), 'збій звʼязку лишається помилкою, без попередження про відсутній ланцюжок');
    }

    /** Відповідь Checkbox на GET відсутнього ланцюжка. */
    private function missing(): array
    {
        return [
            ['message' => 'Prepayment receipt relation not found', 'http_code' => 404, 'response' => ['message' => 'Prepayment receipt relation not found']],
            ['message' => 'Prepayment receipt relation not found'],
        ];
    }

    /** @param array $script [errors, response] — одна відповідь API */
    private function prepayment(?object $logger, array ...$script): CheckboxPrepaymentHelper
    {
        $helper = new class ($script) extends CheckboxPrepaymentHelper {
            public function __construct(private array $script) {}
            protected function makeApiRequest(string $url, array $params = [], array $requestParams = [])
            {
                [$errors, $response] = array_shift($this->script);
                // Як processApiResponse(): на HTTP-помилці errors несуть адресу запиту.
                if (isset($errors['http_code'])) {
                    $errors['requestData'] = ['url' => 'https://checkbox.test/api/v1/' . $url];
                }
                $this->errors = $errors;

                return $response;
            }
        };

        Closure::bind(function () use ($logger) {
            $this->accessToken = 'token';
            $this->logger = $logger;
        }, $helper, CheckboxApiHelper::class)();

        return $helper;
    }

    /** @param array $script відповіді на послідовні GET стану */
    private function receipts(array $script): object
    {
        $row = (object) [
            'id'              => 10,
            'order_id'        => 7,
            'receipt_id'      => 'advance',
            'receipt_type'    => FiscalReceiptsEntity::TYPE_PREPAYMENT,
            'relation_id'     => 'relation-1',
            'chain_status'    => 'PARTIAL_PAID',
            'chain_paid_sum'  => 30000,
            'chain_total_sum' => 100000,
        ];
        $entity = new class ($row) {
            public function __construct(private object $row) {}
            public function order($order = null, array $additionalData = []) { return $this; }
            public function findOne(array $filter = []) { return $this->row; }
            public function update($id, $state) { foreach ((array) $state as $k => $v) { $this->row->$k = $v; } return $id; }
        };
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn ($class) => $class === FiscalReceiptsEntity::class ? $entity : null);

        $logger = $this->logger();
        $prepayment = $this->prepayment($logger, ...$script);
        $helper = new class extends CheckboxReceiptsHelper {
            public function __construct() {}
        };
        $translations = $this->createStub(BackendTranslations::class);
        $translations->method('getTranslation')->willReturnArgument(0);
        Closure::bind(function () use ($factory, $prepayment) {
            $this->entityFactory = $factory;
            $this->prepaymentHelper = $prepayment;
        }, $helper, CheckboxReceiptsHelper::class)();
        Closure::bind(function () use ($logger, $translations) {
            $this->logger = $logger;
            $this->translations = $translations;
        }, $helper, CheckboxApiHelper::class)();
        Closure::bind(function () use ($logger, $translations) {
            $this->translations = $translations;
        }, $prepayment, CheckboxApiHelper::class)();

        return (object) ['helper' => $helper, 'row' => $row, 'logger' => $logger, 'prepayment' => $prepayment];
    }

    private function logger(): object
    {
        return new class extends AbstractLogger {
            public array $records = [];
            public function log($level, string|\Stringable $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string) $message, 'context' => $context];
            }
        };
    }
}
