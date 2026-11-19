<?php

namespace Modules\Sviat\Checkbox;

use Okay\Core\Modules\EntityField;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;

/** Колонка рядків перевізників на способах оплати (1.8.0): до оновлення SELECT із нею падав би мовчки. */
class CarrierPaymentsColumnTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox';

    public function testColumnIsNullableText(): void
    {
        $field = Init::carrierPaymentsColumn();

        self::assertSame(Init::CARRIER_PAYMENTS_FIELD, $field->getName());
        self::assertTrue($field->isNullable(), 'без збережених рядків — NULL, тобто рядок способу оплати');
        self::assertSame('text', strtolower($field->getType()));
    }

    public function testFlagOnlyWhenTheTableHasTheColumn(): void
    {
        $migrated = [];
        $flagged = 0;
        $migrate = static function (EntityField $field) use (&$migrated): void {
            $migrated[] = $field->getName();
        };
        $flag = static function () use (&$flagged): void {
            $flagged++;
        };

        self::assertFalse(Init::addCarrierPaymentsColumnWith($migrate, static fn(): array => ['id', 'module'], $flag), 'ALTER не пройшов');
        self::assertSame(0, $flagged);
        self::assertTrue(Init::addCarrierPaymentsColumnWith($migrate, static fn(): array => ['id', Init::CARRIER_PAYMENTS_FIELD], $flag));
        self::assertSame(1, $flagged);
        self::assertSame([Init::CARRIER_PAYMENTS_FIELD, Init::CARRIER_PAYMENTS_FIELD], $migrated);
    }

    public function testUpdateMethodAndVersion(): void
    {
        $manifest = json_decode((string)file_get_contents(self::MODULE . '/Init/module.json'), true);

        self::assertTrue(method_exists(Init::class, 'update_1_8_0'));
        self::assertSame('1.8.0', $manifest['version']);
    }

    public function testInitRegistersColumnOnlyAfterTheUpdate(): void
    {
        $source = (string)file_get_contents(self::MODULE . '/Init/Init.php');
        $init = substr($source, (int)strpos($source, 'public function init()'));

        self::assertSame(1, preg_match(
            '~if \(\$settings->get\(self::CARRIER_PAYMENTS_READY\)\) \{\s*\$this->registerEntityField\(PaymentsEntity::class, self::CARRIER_PAYMENTS_FIELD\);~',
            $init
        ));
    }
}
