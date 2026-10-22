<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxOrderPaymentGuard;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Замовлення, створене в адмінці без способу оплати, Checkbox не має з чим
 * фіскалізувати. Перемикач у налаштуваннях модуля забороняє таке створення.
 */
class RequirePaymentOnCreateTest extends TestCase
{
    /**
     * @dataProvider blockedProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('blockedProvider')]
    public function testNewOrderWithoutPaymentIsBlockedWhenEnabled($paymentMethodId): void
    {
        self::assertTrue(CheckboxOrderPaymentGuard::blocksCreation(true, self::order(null, $paymentMethodId)));
    }

    public static function blockedProvider(): array
    {
        return [
            'option "not selected"' => [0],
            'string zero from form' => ['0'],
            'empty string'          => [''],
            'missing'               => [null],
        ];
    }

    public function testNewOrderWithPaymentPasses(): void
    {
        self::assertFalse(CheckboxOrderPaymentGuard::blocksCreation(true, self::order(null, '6')));
    }

    /** Контроль: без перемикача нічого не блокується, інакше тест вище був би зеленим і на «блокувати все». */
    public function testNothingIsBlockedWhenDisabled(): void
    {
        self::assertFalse(CheckboxOrderPaymentGuard::blocksCreation(false, self::order(null, 0)));
    }

    /** Вимога стосується створення: редагування наявного замовлення не зачіпається. */
    public function testExistingOrderIsNotBlocked(): void
    {
        self::assertFalse(CheckboxOrderPaymentGuard::blocksCreation(true, self::order(42, 0)));
    }

    private static function order($id, $paymentMethodId): stdClass
    {
        $order = new stdClass();
        $order->id = $id;
        $order->payment_method_id = $paymentMethodId;

        return $order;
    }
}
