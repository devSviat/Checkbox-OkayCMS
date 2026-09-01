<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Без токена жоден метод не має йти в мережу.
 *
 * Заглушки тут навмисно НЕ виставляють $errors, хоч справжній getAccessToken()
 * це вже робить: так перевіряється саме умова на порожній токен, окремо від
 * умови на errors. Захист має триматись на кожній з них поодинці.
 */
class AuthorisationGuardTest extends TestCase
{
    /** @return array<int, array{0: string, 1: array<int, mixed>}> */
    public static function methodsThatTalkToApi(): array
    {
        return [
            'createShift'      => ['createShift', []],
            'checkShiftStatus' => ['checkShiftStatus', ['shift-id']],
            'getShiftsList'    => ['getShiftsList', []],
            'closeShift'       => ['closeShift', []],
            'getCashierInfo'   => ['getCashierInfo', []],
        ];
    }

    /** @param array<int, mixed> $args */
    /** @dataProvider methodsThatTalkToApi */
    #[DataProvider('methodsThatTalkToApi')]
    public function testNoRequestIsMadeWithoutAToken(string $method, array $args): void
    {
        $helper = $this->helperWithUnusableCredentials();

        $result = $helper->{$method}(...$args);

        self::assertSame([], $helper->requestedUrls, "{$method}() пішов у мережу без токена");
        self::assertSame(['message' => 'Заповніть параметри модуля'], $result);
    }

    /** Контроль: із робочим токеном ті самі методи мусять доходити до запиту. */
    public function testRequestIsMadeOnceTokenIsAvailable(): void
    {
        $helper = $this->helperWithUnusableCredentials();
        $helper->tokenToIssue = 'working-token';

        $helper->createShift();

        self::assertSame(['shifts'], $helper->requestedUrls);
    }

    /**
     * Той самий захист у хелпері чеків. Тут ціна помилки вища за марний запит:
     * далі по методу йдуть звернення до сервісів, яких без токена не мало б
     * бути видно взагалі.
     */
    public function testReceiptsHelperStopsBeforeTheOrderLookup(): void
    {
        $helper = new class extends CheckboxReceiptsHelper {
            public function __construct()
            {
                // Батьківський конструктор іде в ServiceLocator по налаштування.
            }

            /** @return array|false */
            public function getAccessToken()
            {
                return ['message' => 'Заповніть параметри модуля'];
            }
        };

        self::assertSame(['message' => 'Заповніть параметри модуля'], $helper->createReceipt(22968));
    }

    /**
     * Контроль до попереднього тесту: із токеном метод мусить іти далі. Без цієї
     * перевірки захист, що блокує завжди, теж лишався б зеленим.
     *
     * Ознака «пішов далі» — звернення до entityFactory, якої в голому підкласі
     * немає; сам виняток тут не мета, а найближчий слід за захистом.
     */
    public function testReceiptsHelperProceedsOnceTokenIsAvailable(): void
    {
        $helper = new class extends CheckboxReceiptsHelper {
            public function __construct()
            {
            }

            /** @return array|false */
            public function getAccessToken()
            {
                $this->accessToken = 'working-token';

                return ['access_token' => 'working-token'];
            }
        };

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('entityFactory must not be accessed before initialization');

        $helper->createReceipt(22968);
    }

    private function helperWithUnusableCredentials(): CheckboxShiftsHelper
    {
        return new class extends CheckboxShiftsHelper {
            /** @var array<int, string> */
            public array $requestedUrls = [];
            public ?string $tokenToIssue = null;

            public function __construct()
            {
                // Батьківський конструктор іде в ServiceLocator по налаштування.
            }

            /**
             * Токен не видано і errors не виставлено — найгірший для захисту
             * випадок: спрацювати мусить сама лише перевірка на порожній токен.
             *
             * @return array|false
             */
            public function getAccessToken()
            {
                $this->accessToken = $this->tokenToIssue;

                return $this->tokenToIssue === null
                    ? ['message' => 'Заповніть параметри модуля']
                    : ['access_token' => $this->tokenToIssue];
            }

            /** @return array|string|false */
            protected function makeApiRequest(string $url, array $params = [], array $requestParams = [])
            {
                $this->requestedUrls[] = $url;

                return [];
            }
        };
    }
}
