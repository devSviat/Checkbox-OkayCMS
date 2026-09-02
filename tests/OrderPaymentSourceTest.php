<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentSources;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Джерело коштів для автоматичного чека післяплати виводиться зі способу оплати
 * замовлення. Платіжних систем у магазина стільки, скільки він завів, і спосіб
 * оплати «Платіж LiqPay» мусить вести до свого запису: чужий дав би податковій
 * інший засіб платежу, ніж той, яким клієнт справді заплатив.
 */
class OrderPaymentSourceTest extends TestCase
{
    private const CONFIGURED = [
        'cash',
        'bank_account',
        'internet_banking',
        'integrator:NovaPay',
        'integrator:LiqPay',
        'integrator:WayForPay',
    ];

    private const NAMES = ['integrator' => ['NovaPay', 'LiqPay', 'WayForPay']];

    /** @return array<string, array{0: string, 1: string, 2: string}> */
    public static function paymentMethodProvider(): array
    {
        return [
            'NovaPay у мітці'          => ['Платіж NovaPay', 'CASHLESS', 'integrator:NovaPay'],
            'LiqPay у мітці'           => ['Платіж LiqPay', 'CASHLESS', 'integrator:LiqPay'],
            'WayForPay іншими словами' => ['Оплата через WayForPay', 'CASHLESS', 'integrator:WayForPay'],
            'регістр не має значення'  => ['платіж liqpay', 'CASHLESS', 'integrator:LiqPay'],
            'жодного інтегратора'      => ['За реквізитами (IBAN)', 'CASHLESS', 'bank_account'],
            'готівка при отриманні'    => ['Готівка', 'CASH', 'cash'],
            'мітки немає взагалі'      => ['', 'CASHLESS', 'bank_account'],
        ];
    }

    /**
     * @dataProvider paymentMethodProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('paymentMethodProvider')]
    public function testSourceFollowsThePaymentMethod(string $label, string $type, string $expected): void
    {
        $helper = $this->buildHelper($label, $type, self::CONFIGURED, self::NAMES);

        self::assertSame($expected, $helper->orderPaymentSource(42));
    }

    /**
     * Магазин прибрав LiqPay зі списку — автоматика не має підставляти джерело,
     * якого більше немає. Порожня мітка тут дорівнює «не інтегратор».
     */
    public function testRemovedPaymentSystemIsNotGuessed(): void
    {
        $helper = $this->buildHelper(
            'Платіж LiqPay',
            'CASHLESS',
            ['cash', 'bank_account', 'integrator:NovaPay'],
            ['integrator' => ['NovaPay']]
        );

        self::assertSame('bank_account', $helper->orderPaymentSource(42));
    }

    /**
     * @param string[] $configured
     * @param array<string, string[]> $names
     */
    private function buildHelper(string $label, string $type, array $configured, array $names): CheckboxReceiptsHelper
    {
        $order = new \stdClass();
        $order->id = 42;
        $order->payment_method_id = 18;

        $method = new \stdClass();
        $method->id = 18;
        $method->sviat__checkbox__payment_label = $label;
        $method->sviat__checkbox__payment_type = $type;

        $orders = $this->createStub(OrdersEntity::class);
        $orders->method('get')->willReturn($order);

        $payments = $this->createStub(PaymentsEntity::class);
        $payments->method('findOne')->willReturn($method);

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(
            fn (string $class) => $class === OrdersEntity::class ? $orders : $payments
        );

        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturn(CheckboxPaymentSources::encode($configured, $names));

        $helper = (new ReflectionClass(CheckboxReceiptsHelper::class))->newInstanceWithoutConstructor();

        // Closure::bind, а не ReflectionProperty: на PHP 8.0 запис у захищену
        // властивість вимагає setAccessible(), а з 8.1 той самий виклик
        // задепрекейчено. Модуль їде на обох рушіях.
        Closure::bind(
            function () use ($factory, $settings) {
                $this->entityFactory = $factory;
                $this->settings = $settings;
            },
            $helper,
            CheckboxReceiptsHelper::class
        )();

        return $helper;
    }
}
