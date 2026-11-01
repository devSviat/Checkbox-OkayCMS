<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Security\PlainSettingValue;
use Okay\Modules\Sviat\Checkbox\Security\ReturnUrl;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Форми адмінки на стоку не мають CSRF-захисту до контролера, тому модуль сам
 * перевіряє походження запиту й те, що потрапляє в налаштування.
 */
class AdminFormSafetyTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox';

    #[DataProvider('plainValues')]
    public function testOrdinaryTextIsAccepted(string $value): void
    {
        self::assertTrue(PlainSettingValue::isPlain($value));
    }

    public static function plainValues(): array
    {
        return [
            'empty'    => [''],
            'login'    => ['test_cashier'],
            'password' => ['a:b;c"d'],
            'receipt'  => ['Дякуємо за покупку! {$order_id}'],
            'key'      => ['0123456789abcdef'],
        ];
    }

    #[DataProvider('serialisedValues')]
    public function testSerialisedValuesAreRefused($value): void
    {
        self::assertFalse(PlainSettingValue::isPlain($value));
    }

    public static function serialisedValues(): array
    {
        return [
            'object' => ['O:8:"stdClass":0:{}'],
            'array'  => ['a:0:{}'],
            'false'  => ['b:0;'],
            'string' => ['s:1:"x";'],
            'number' => ['i:5;'],
            'null'   => [null],
            'list'   => [['x']],
        ];
    }

    #[DataProvider('safeReturns')]
    public function testSameSitePathsAreKept(string $value): void
    {
        self::assertSame($value, ReturnUrl::safe($value));
    }

    public static function safeReturns(): array
    {
        return [
            'admin page' => ['/backend/index.php?controller=OrdersAdmin&status=1'],
            'subdir'     => ['/shop/backend/index.php?controller=OrderAdmin&id=5'],
        ];
    }

    #[DataProvider('unsafeReturns')]
    public function testOtherTargetsAreRefused($value): void
    {
        self::assertNull(ReturnUrl::safe($value));
    }

    public static function unsafeReturns(): array
    {
        return [
            'javascript'  => ['javascript:alert(1)'],
            'mixed case'  => ['JaVaScRiPt:alert(1)'],
            'data'        => ['data:text/html,x'],
            'absolute'    => ['https://evil.example/'],
            'protocol'    => ['//evil.example/'],
            'backslash'   => ['/\\evil.example/'],
            'newline'     => ["/x\r\nSet-Cookie: a=1"],
            'tab trick'   => ["/\t/evil.example"],
            'empty'       => [''],
            'array'       => [['/x']],
        ];
    }

    #[DataProvider('adminForms')]
    public function testEveryAdminWriteChecksOrigin(string $controller): void
    {
        $source = (string) file_get_contents(self::MODULE . "/Backend/Controllers/$controller.php");

        self::assertStringContainsString('RequestOrigin::isFromThisSite()', $source, $controller);
    }

    public static function adminForms(): array
    {
        return [['CheckboxAdmin'], ['TaxGroupAdmin'], ['TaxGroupsAdmin']];
    }

    public function testTemplatesPrintReturnOnlyAfterTheCheck(): void
    {
        foreach (['checkbox_admin.tpl', 'checkbox_tax.tpl'] as $template) {
            $source = (string) file_get_contents(self::MODULE . "/Backend/design/html/$template");

            self::assertStringNotContainsString('$smarty.get.return', $source, $template);
            self::assertStringContainsString('{$checkboxReturnUrl|escape}', $source, $template);
        }
    }

    public function testCredentialsAreNotPrintedIntoTheForm(): void
    {
        $source = (string) file_get_contents(self::MODULE . '/Backend/design/html/checkbox_admin.tpl');

        self::assertStringNotContainsString('$settings->sviat__checkbox__cashier_password', $source);
        self::assertStringNotContainsString('$settings->sviat__checkbox__cashier_license_key', $source);
    }
}
