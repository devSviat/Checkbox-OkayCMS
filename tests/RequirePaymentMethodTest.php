<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxOrderPaymentGuard;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Замовлення без способу оплати Checkbox не має з чим фіскалізувати. Перемикач
 * у налаштуваннях модуля забороняє зберігати таке замовлення в адмінці — і
 * нове, і наявне.
 */
class RequirePaymentMethodTest extends TestCase
{
    /**
     * @dataProvider withoutPaymentProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('withoutPaymentProvider')]
    public function testOrderWithoutPaymentIsBlockedWhenEnabled($id, $paymentMethodId): void
    {
        self::assertTrue(CheckboxOrderPaymentGuard::blocksSave(true, self::order($id, $paymentMethodId)));
    }

    public static function withoutPaymentProvider(): array
    {
        return [
            'new, option "not selected"'      => [null, 0],
            'new, string zero from form'      => [null, '0'],
            'new, empty string'               => [null, ''],
            'new, missing'                    => [null, null],
            'existing, option "not selected"' => [42, '0'],
        ];
    }

    /**
     * @dataProvider withPaymentProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('withPaymentProvider')]
    public function testOrderWithPaymentPasses($id): void
    {
        self::assertFalse(CheckboxOrderPaymentGuard::blocksSave(true, self::order($id, '6')));
    }

    public static function withPaymentProvider(): array
    {
        return [
            'new'      => [null],
            'existing' => [42],
        ];
    }

    /** Контроль: без перемикача нічого не блокується, інакше тести вище були б зелені й на «блокувати все». */
    public function testNothingIsBlockedWhenDisabled(): void
    {
        self::assertFalse(CheckboxOrderPaymentGuard::blocksSave(false, self::order(null, 0)));
        self::assertFalse(CheckboxOrderPaymentGuard::blocksSave(false, self::order(42, 0)));
    }

    private static function order($id, $paymentMethodId): stdClass
    {
        $order = new stdClass();
        $order->id = $id;
        $order->payment_method_id = $paymentMethodId;

        return $order;
    }
}
