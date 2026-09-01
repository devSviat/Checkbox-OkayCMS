<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\Checkbox\Entities\CashierShiftsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use ReflectionClass;
use ReflectionMethod;

/**
 * Збій фіскальної операції має лишати слід — і не має лишати облікових даних.
 *
 * Друге не менш важливе за перше: `errors['requestData']` несе заголовки з
 * Bearer-токеном і ліцензійним ключем, а запит токена — логін і пароль касира.
 * Лог читають ширше, ніж базу.
 */
class FailureLoggingTest extends TestCase
{
    public function testFailureIsLoggedWithMessageAndHttpCode(): void
    {
        $logger = $this->logger();
        $helper = $this->apiHelper($logger, [
            'message'   => 'Зміна не відкрита',
            'http_code' => 400,
        ]);

        $this->logFailure($helper, 'receipt not fiscalised', ['order_id' => 101]);

        self::assertCount(1, $logger->records);
        self::assertSame('Sviat/Checkbox: receipt not fiscalised', $logger->records[0]['message']);
        self::assertSame('Зміна не відкрита', $logger->records[0]['context']['error']);
        self::assertSame(400, $logger->records[0]['context']['http_code']);
        self::assertSame(101, $logger->records[0]['context']['order_id']);
    }

    /** Головна перевірка цього файла. */
    public function testCredentialsNeverReachTheLog(): void
    {
        $logger = $this->logger();
        $helper = $this->apiHelper($logger, [
            'message'     => 'HTTP Error 401',
            'http_code'   => 401,
            'requestData' => [
                'url'     => 'https://api.checkbox.in.ua/api/v1/cashier/signin',
                'headers' => [
                    'Authorization: Bearer secret-token-value',
                    'X-License-Key: secret-license-value',
                ],
                'data'    => ['login' => 'cashier-login', 'password' => 'secret-password'],
            ],
            'response'    => ['message' => 'Unauthorized'],
        ]);

        $this->logFailure($helper, 'authorisation failed');

        $dump = json_encode($logger->records, JSON_UNESCAPED_UNICODE);
        foreach (['secret-token-value', 'secret-license-value', 'secret-password', 'cashier-login', 'Authorization'] as $secret) {
            self::assertStringNotContainsString($secret, (string)$dump, "у лог потрапило: $secret");
        }
        self::assertArrayNotHasKey('requestData', $logger->records[0]['context']);
    }

    /** Крон пише щопʼять хвилин, а текст помилки приходить ззовні. */
    public function testLongMessageIsTruncated(): void
    {
        $logger = $this->logger();
        $helper = $this->apiHelper($logger, ['message' => str_repeat('я', 5000)]);

        $this->logFailure($helper, 'receipt not fiscalised');

        self::assertSame(500, mb_strlen($logger->records[0]['context']['error']));
    }

    /** Невідомий формат помилки не має ламати сам запис у лог. */
    public function testMissingMessageDegradesToUnknown(): void
    {
        $logger = $this->logger();
        $helper = $this->apiHelper($logger, ['http_code' => 500]);

        $this->logFailure($helper, 'receipt not fiscalised');

        self::assertSame('unknown', $logger->records[0]['context']['error']);
    }

    public function testWithoutALoggerNothingHappens(): void
    {
        $this->expectNotToPerformAssertions();

        $helper = $this->apiHelper(null, ['message' => 'збій']);

        $this->logFailure($helper, 'receipt not fiscalised');
    }

    /**
     * Наскрізь: зміна, яку не вдалося відкрити, лишає запис. Без неї не піде
     * жоден чек, а нагорі це виглядає просто як «чека немає».
     */
    public function testShiftThatCannotBeOpenedIsLogged(): void
    {
        $logger = $this->logger();

        $shifts = new class extends CheckboxShiftsHelper {
            public function __construct()
            {
                // Батьківський конструктор іде в ServiceLocator по налаштування.
            }

            public function createShift()
            {
                $this->errors['message'] = 'Каса недоступна';

                return false;
            }
        };

        $entities = new class {
            public function getActiveShift()
            {
                return false;
            }
        };

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(
            function ($class) use ($entities) {
                return $class === CashierShiftsEntity::class ? $entities : null;
            }
        );

        Closure::bind(
            function () use ($factory, $logger) {
                $this->entityFactory = $factory;
                $this->logger = $logger;
            },
            $shifts,
            CheckboxApiHelper::class
        )();

        self::assertNull($shifts->openShiftIfNeeded());
        self::assertCount(1, $logger->records);
        self::assertSame('Sviat/Checkbox: cashier shift not opened', $logger->records[0]['message']);
        self::assertSame('Каса недоступна', $logger->records[0]['context']['error']);
    }

    /**
     * Незаповнені облікові дані касира мусять лишати в логу саме «зміна не
     * відкрилась». Захист від запиту без токена повертає сюди масив з message,
     * а не false, — і без окремої перевірки код доходив би до гілки «відкрилась
     * без id», тобто лог стверджував би, що зміна створилась.
     */
    public function testEmptyCredentialsAreLoggedAsShiftNotOpened(): void
    {
        $logger = $this->logger();

        $shifts = new class extends CheckboxShiftsHelper {
            public function __construct()
            {
                // Батьківський конструктор іде в ServiceLocator по налаштування.
            }

            /**
             * Повторює ранній вихід CheckboxApiHelper::getAccessToken() при
             * порожніх облікових даних.
             */
            public function getAccessToken()
            {
                $this->errors['message'] = 'Заповніть параметри модуля';

                return ['message' => $this->errors['message']];
            }
        };

        $entities = new class {
            public function getActiveShift()
            {
                return false;
            }
        };

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(
            function ($class) use ($entities) {
                return $class === CashierShiftsEntity::class ? $entities : null;
            }
        );

        Closure::bind(
            function () use ($factory, $logger) {
                $this->entityFactory = $factory;
                $this->logger = $logger;
            },
            $shifts,
            CheckboxApiHelper::class
        )();

        self::assertNull($shifts->openShiftIfNeeded());
        self::assertCount(1, $logger->records);
        self::assertSame('Sviat/Checkbox: cashier shift not opened', $logger->records[0]['message']);
        self::assertSame('Заповніть параметри модуля', $logger->records[0]['context']['error']);
    }

    // ── допоміжне ─────────────────────────────────────────────────────────

    /** @param array<string, mixed> $errors */
    private function apiHelper(?object $logger, array $errors): CheckboxApiHelper
    {
        $helper = (new ReflectionClass(CheckboxApiHelper::class))->newInstanceWithoutConstructor();

        Closure::bind(
            function () use ($logger, $errors) {
                $this->logger = $logger;
                $this->errors = $errors;
            },
            $helper,
            CheckboxApiHelper::class
        )();

        return $helper;
    }

    /** @param array<string, mixed> $context */
    private function logFailure(object $helper, string $event, array $context = []): void
    {
        $method = new ReflectionMethod(CheckboxApiHelper::class, 'logFailure');
        if (PHP_VERSION_ID < 80100) {
            $method->setAccessible(true);
        }
        $method->invoke($helper, $event, $context);
    }

    private function logger(): object
    {
        return new class extends AbstractLogger {
            /** @var list<array{level: mixed, message: string, context: array}> */
            public array $records = [];

            public function log($level, $message, array $context = []): void
            {
                $this->records[] = ['level' => $level, 'message' => (string)$message, 'context' => $context];
            }
        };
    }
}
