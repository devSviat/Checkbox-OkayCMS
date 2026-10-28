<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Накладна Нової Пошти бере з отримувача лише те, що не покрито авансом.
 * Без цього клієнт з авансом платив би на пошті всю суму вдруге.
 */
class NovaPoshtaAmountDueTest extends TestCase
{
    /** @return iterable<string, array{?array, float, float}> */
    public static function chains(): iterable
    {
        yield 'аванс внесено, решта при отриманні' => [self::chain('PARTIAL_PAID', 120000, 245500), 2455.0, 1255.0];
        yield 'післяплату вже отримано' => [self::chain('FULL_PAID', 245500, 245500), 2455.0, 0.0];
        yield 'аванс повернуто' => [self::chain('CANCELLED', 120000, 245500), 2455.0, 2455.0];
        yield 'стан невідомий' => [self::chain('unknown', null, null), 2455.0, 2455.0];
        yield 'без авансу' => [null, 2455.0, 2455.0];
        yield 'замовлення зменшили після авансу' => [self::chain('PARTIAL_PAID', 120000, 245500), 900.0, 900.0];
    }

    #[DataProvider('chains')]
    public function testAmountDueOnPickup(?array $chain, float $due, float $expected): void
    {
        $helper = $this->createStub(CheckboxHelper::class);
        $helper->method('orderChainStatus')->willReturn($chain);
        $extender = new ReflectionClass(BackendExtender::class)->newInstanceWithoutConstructor();
        $property = new ReflectionProperty($extender, 'checkboxHelper');
        if (PHP_VERSION_ID < 80100) {
            $property->setAccessible(true);
        }
        $property->setValue($extender, $helper);

        self::assertSame($expected, $extender->amountDueOnPickup($due, (object) ['id' => 24793]));
    }

    private static function chain(string $status, ?int $paid, ?int $total): array
    {
        return [
            'pre_payment_status' => $status,
            'relation_id' => 'chain',
            'paid_sum' => $paid,
            'total_sum' => $total,
            'left_to_pay' => ($paid === null || $total === null) ? null : max(0, $total - $paid),
        ];
    }
}
