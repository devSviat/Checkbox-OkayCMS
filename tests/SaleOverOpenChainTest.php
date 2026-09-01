<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\BackendTranslations;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxChainDecision;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Замовлення з живим ланцюжком не має отримати повний чек продажу.
 *
 * fiscaliseOrder() цю розвилку тримає, але createReceipt() доступний і напряму —
 * ajax-ендпоінтом кнопки «Фіскалізувати чек». Ховання кнопки в шаблоні захистом
 * не є: стара вкладка або друга сесія менеджера дійдуть до методу однаково, а
 * маршрут іде повз авторизацію адмінки.
 */
class SaleOverOpenChainTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function liveChainStatuses(): array
    {
        return [
            'відкритий аванс'   => ['PARTIAL_PAID'],
            'ланцюжок закрито'  => ['FULL_PAID'],
            'стан невідомий'    => [CheckboxChainDecision::STATUS_UNKNOWN],
        ];
    }

    /** @dataProvider liveChainStatuses */
    #[DataProvider('liveChainStatuses')]
    public function testSaleIsRefusedWhileChainExists(string $status): void
    {
        $helper = $this->helperWithChain($status);

        $result = $helper->createReceipt(22991);

        self::assertIsObject($result, "статус {$status}: чек продажу не відмовлено");
        self::assertSame('ПЕРЕКЛАД:sviat__checkbox__errors_chain_is_open', $result->message);
    }

    /** Контроль: без ланцюжка звичайний продаж мусить іти своїм шляхом. */
    public function testSaleProceedsWithoutAChain(): void
    {
        $helper = $this->helperWithChain(null);

        // Ознака «пішов далі» — звернення до entityFactory, якої в голому
        // підкласі немає. Сам виняток тут не мета, а найближчий слід за перевіркою.
        $this->expectException(\Error::class);
        $this->expectExceptionMessage('entityFactory must not be accessed before initialization');

        $helper->createReceipt(22991);
    }

    /**
     * Скасований ланцюжок навпаки МУСИТЬ пускати звичайний чек продажу.
     *
     * Після повернення ланцюжка це єдиний законний шлях фіскалізувати
     * замовлення. Блокувати тут означало б замкнути менеджера в глухому куті:
     * ланцюжка вже немає, а продаж заборонено.
     */
    /** @dataProvider cancelledChainStatuses */
    #[DataProvider('cancelledChainStatuses')]
    public function testSaleIsAllowedAfterChainWasReturned(string $status): void
    {
        $helper = $this->helperWithChain($status);

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('entityFactory must not be accessed before initialization');

        $helper->createReceipt(22991);
    }

    /** @return array<string, array{0: string}> */
    public static function cancelledChainStatuses(): array
    {
        return [
            'ланцюжок повернено'   => ['CANCELLED'],
            'частково повернено'   => ['PARTIAL_CANCELLED'],
        ];
    }

    /** Повернення ланцюжок не блокує — у нього власний шлях. */
    public function testReturnIsNotBlockedByAnOpenChain(): void
    {
        $helper = $this->helperWithChain('PARTIAL_PAID');

        $this->expectException(\Error::class);
        $this->expectExceptionMessage('entityFactory must not be accessed before initialization');

        $helper->createReceipt(22991, true);
    }

    private function helperWithChain(?string $status): CheckboxReceiptsHelper
    {
        $helper = new class extends CheckboxReceiptsHelper {
            public ?string $chainStatus = null;

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
                return $this->chainStatus === null
                    ? null
                    : ['pre_payment_status' => $this->chainStatus, 'relation_id' => 'prepayment-22991'];
            }
        };
        $helper->chainStatus = $status;

        $translations = $this->createStub(BackendTranslations::class);
        $translations->method('getTranslation')->willReturnCallback(
            function ($key) {
                return 'ПЕРЕКЛАД:' . $key;
            }
        );

        Closure::bind(
            function () use ($translations) {
                $this->translations = $translations;
            },
            $helper,
            CheckboxApiHelper::class
        )();

        return $helper;
    }
}
