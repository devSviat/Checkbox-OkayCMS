<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxSmsBalance as Balance;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Баланс SMS у Checkbox: копійки, з яких Checkbox списує кожен чек на телефон.
 * Без автосписання порожній баланс мовчки зупиняє відправку.
 */
class SmsBalanceTest extends TestCase
{
    /** @return iterable<string, array{Balance, ?string}> */
    public static function warnings(): iterable
    {
        yield 'plenty' => [new Balance(true, 37844), null];
        yield 'below 50 UAH' => [new Balance(true, 4999), Balance::WARNING_LOW];
        yield 'exactly 50 UAH is still fine' => [new Balance(true, 5000), null];
        yield 'empty' => [new Balance(true, 0), Balance::WARNING_LOW];
        yield 'service off outranks balance' => [new Balance(false, 37844), Balance::WARNING_OFF];
    }

    #[DataProvider('warnings')]
    public function testWarning(Balance $balance, ?string $expected): void
    {
        self::assertSame($expected, $balance->warning());
    }

    public function testFromCheckboxResponse(): void
    {
        $balance = Balance::fromApi(['can_send_sms' => true, 'billing_enabled' => true, 'balance' => 37844]);

        self::assertNotNull($balance);
        self::assertTrue($balance->enabled);
        self::assertSame(37844, $balance->balanceKopiyky);
        self::assertSame('378,44', $balance->hryvnias());
        self::assertNull(Balance::fromApi(['can_send_sms' => true]), 'без суми баланс невідомий');
        self::assertNull(Balance::fromApi(['balance' => 5]));
    }

    /** Кнопка — єдиний шлях у Checkbox по баланс: ні розкладу, ні запиту на відкритті сторінки. */
    public function testBalanceIsCheckedOnlyByButton(): void
    {
        $module = __DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox';
        $init = (string) file_get_contents($module . '/Init/Init.php');
        $admin = (string) file_get_contents($module . '/Backend/Controllers/CheckboxAdmin.php');
        $template = (string) file_get_contents($module . '/Backend/design/html/checkbox_admin.tpl');

        self::assertStringNotContainsString('phoneDeliveryStatus', $init);
        self::assertSame(1, substr_count($admin, 'phoneDeliveryStatus()'));
        self::assertMatchesRegularExpression('~if \(\$this->request->method\(\'post\'\) && \$this->request->post\(\'sviat__checkbox__sms_check_form\'\)\)~', $admin);
        self::assertMatchesRegularExpression('~<form id="checkbox_sms_check" method="post"[^>]*>\s*<input type="hidden" name="session_id" value="\{\$smarty\.session\.id\}">~', $template);
    }
}
