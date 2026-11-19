<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\Checkbox\Carriers\CodCarriers;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentProfiles;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Блок «Чек Checkbox» на сторінці способу оплати: рядок на кожного перевізника з увімкненим модулем, прихований,
 * поки його післяплату не позначено. Модуль перевізника реєструє свою колонку в сутності способів оплати лише
 * ввімкненим, тож тест реєструє її сам і повертає список полів після себе.
 */
class CarrierRowsTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox';
    private const array COD_FIELDS = ['sviat__novaposhta__cod', 'sviat__ukrposhta__cod', 'sviat__rozetka__cod'];

    /** @var list<string> */
    private array $fields = [];
    private ?string $compileDir = null;

    protected function setUp(): void
    {
        $this->fields = PaymentsEntity::getFields();
        foreach (self::COD_FIELDS as $field) {
            PaymentsEntity::addField($field);
        }
    }

    protected function tearDown(): void
    {
        new ReflectionProperty(PaymentsEntity::class, 'fields')->setValue(null, $this->fields);
        $_POST = [];
        if ($this->compileDir !== null) {
            array_map(unlink(...), glob($this->compileDir . '/*') ?: []);
            rmdir($this->compileDir);
        }
    }

    private function settings(array $values = ['sviat__rozetka__cod_fiscal' => 'shop', Init::CARRIER_PAYMENTS_READY => '1']): Settings
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(static fn(string $name): ?string => $values[$name] ?? null);

        return $settings;
    }

    private function profiles(): CheckboxPaymentProfiles
    {
        $factory = $this->createStub(EntityFactory::class);

        return new CheckboxPaymentProfiles($factory, new CodCarriers($factory, $this->settings()));
    }

    private static function method(array $over = []): object
    {
        return (object)($over + [
            'id' => 18, 'module' => 'null',
            Init::PAYMENT_TYPE_FIELD => 'CASHLESS', Init::PAYMENT_LABEL_FIELD => 'Платіж NovaPay', Init::PAYMENT_SKIP_FIELD => 0,
        ]);
    }

    public function testRowPerEnabledCarrierWithDefaultsOrSavedValues(): void
    {
        $method = self::method([
            'sviat__novaposhta__cod' => 1,
            Init::CARRIER_PAYMENTS_FIELD => '{"rozetka":{"type":"CASH","label":"Свій рядок","skip":true}}',
        ]);

        self::assertSame([
            ['carrier' => 'novaposhta', 'switch' => 'sviat_np_cod', 'on' => true, 'type' => 'CASHLESS', 'label' => 'Платіж NovaPay', 'skip' => false],
            ['carrier' => 'ukrposhta', 'switch' => 'sviat_up_cod', 'on' => false, 'type' => 'CASHLESS', 'label' => 'Післяплата', 'skip' => false],
            ['carrier' => 'rozetka', 'switch' => 'sviat_rz_cod', 'on' => false, 'type' => 'CASH', 'label' => 'Свій рядок', 'skip' => true],
        ], $this->profiles()->rows($method));
    }

    public function testNewRozetkaRowIsRozetkaPay(): void
    {
        $rows = array_column($this->profiles()->rows(self::method(['sviat__rozetka__cod' => 1])), null, 'carrier');

        self::assertSame(['carrier' => 'rozetka', 'switch' => 'sviat_rz_cod', 'on' => true, 'type' => 'CASHLESS', 'label' => 'Платіж ROZETKAPAY', 'skip' => false], $rows['rozetka']);
    }

    /** Сток без модулів перевізників: рядків немає, блок той самий, що до 1.8.0. */
    public function testStockHasNoCarrierRows(): void
    {
        new ReflectionProperty(PaymentsEntity::class, 'fields')->setValue(null, $this->fields);

        self::assertSame([], $this->profiles()->rows(self::method()));
        self::assertSame([], CheckboxPaymentProfiles::fromPost(['rozetka' => ['type' => 'CASHLESS', 'label' => 'x']]));
    }

    public function testFromPostKeepsValidRowsOnly(): void
    {
        $rows = CheckboxPaymentProfiles::fromPost([
            'novaposhta' => ['type' => 'CASHLESS', 'label' => '  Платіж NovaPay  ', 'skip' => '1'],
            'ukrposhta' => ['type' => 'OTHER', 'label' => 'x'],
            'rozetka' => ['type' => 'CASH', 'label' => ['масив']],
            'meest' => ['type' => 'CASH', 'label' => 'x'],
        ]);

        self::assertSame(['novaposhta', 'rozetka'], array_keys($rows));
        self::assertSame(['type' => 'CASHLESS', 'label' => 'Платіж NovaPay', 'skip' => true], $rows['novaposhta']->toArray());
        self::assertSame(['type' => 'CASH', 'label' => '', 'skip' => false], $rows['rozetka']->toArray());
        self::assertSame([], CheckboxPaymentProfiles::fromPost('рядок'));
    }

    /** Надіслані рядки лягають поверх збережених; рядок вимкненого перевізника форма не надсилає, і він лишається. */
    public function testSaveMergesShownRowsOverStored(): void
    {
        $_POST = [
            Init::PAYMENT_TYPE_FIELD => 'CASHLESS',
            Init::PAYMENT_LABEL_FIELD => 'Платіж NovaPay',
            Init::CARRIER_PAYMENTS_FIELD => [
                'rozetka' => ['type' => 'CASHLESS', 'label' => 'Платіж ROZETKAPAY', 'skip' => '1'],
                'novaposhta' => ['type' => 'CASHLESS', 'label' => 'Платіж NovaPay'],
            ],
        ];
        $method = (object)['id' => 18];

        $this->extender('{"ukrposhta":{"type":"CASH","label":"","skip":false},"novaposhta":{"type":"CASH","label":"","skip":false}}')->postPayment($method);

        self::assertSame([
            'ukrposhta' => ['type' => 'CASH', 'label' => '', 'skip' => false],
            'rozetka' => ['type' => 'CASHLESS', 'label' => 'Платіж ROZETKAPAY', 'skip' => true],
        ], json_decode($method->{Init::CARRIER_PAYMENTS_FIELD}, true), 'рядок НП, що дорівнює новому рядку способу оплати, з колонки зникає');
        self::assertSame('Платіж NovaPay', $method->{Init::PAYMENT_LABEL_FIELD});
    }

    /** До update_1_8_0() колонки немає: невідома колонка провалила б запис усього способу оплати. */
    public function testBeforeTheUpdateTheColumnIsNotWritten(): void
    {
        $_POST = [Init::CARRIER_PAYMENTS_FIELD => ['rozetka' => ['type' => 'CASHLESS', 'label' => 'x']]];
        $method = (object)['id' => 18];

        $this->extender(null, $this->settings(['sviat__rozetka__cod_fiscal' => 'shop']))->postPayment($method);

        self::assertFalse(property_exists($method, Init::CARRIER_PAYMENTS_FIELD));
    }

    public function testNothingShownLeavesStoredRows(): void
    {
        $_POST = [Init::PAYMENT_TYPE_FIELD => 'CASH'];
        $method = (object)['id' => 18];

        $this->extender('{"rozetka":{"type":"CASH","label":"","skip":true}}')->postPayment($method);
        self::assertSame('CASH', $method->{Init::PAYMENT_TYPE_FIELD});

        self::assertFalse(property_exists($method, Init::CARRIER_PAYMENTS_FIELD));
    }

    public function testTemplateRendersRowsEscapedAndHidesOffRows(): void
    {
        $method = self::method([Init::PAYMENT_LABEL_FIELD => '<b>мітка</b>']);
        $rows = [
            ['carrier' => 'novaposhta', 'switch' => 'sviat_np_cod', 'on' => true, 'type' => 'CASH', 'label' => '"><script>x</script>', 'skip' => true],
            ['carrier' => 'rozetka', 'switch' => 'sviat_rz_cod', 'on' => false, 'type' => 'CASHLESS', 'label' => 'Платіж ROZETKAPAY', 'skip' => false],
        ];

        $html = $this->render($method, $rows);

        self::assertStringNotContainsString('<script>', $html);
        self::assertStringNotContainsString('<b>мітка', $html);
        self::assertStringContainsString('name="sviat__checkbox__payment_label" value="&lt;b&gt;мітка&lt;/b&gt;"', $html);
        self::assertStringContainsString('name="sviat__checkbox__payment_type"', $html);
        self::assertStringContainsString('name="sviat__checkbox__payment_skip"', $html);
        self::assertMatchesRegularExpression('~<tr class="[^"]*fn_sviat_checkbox_carrier" data-switch="sviat_np_cod">~', $html, 'позначений рядок видно');
        self::assertMatchesRegularExpression('~<tr class="[^"]*fn_sviat_checkbox_carrier hidden" data-switch="sviat_rz_cod">~', $html, 'непозначений прихований');
        self::assertSame(3, substr_count($html, ' disabled'), 'прихований рядок не надсилається: тип, мітка, перемикач');
        self::assertStringNotContainsString('receipt__new', $html, 'позначки «новий» немає: рядок і так заповнений типовими значеннями');
        self::assertStringContainsString('name="sviat__checkbox__carrier_payments[rozetka][label]" value="Платіж ROZETKAPAY"', $html);
        self::assertStringContainsString('sviat__checkbox__receipt_box_note', $html);

        self::assertSame(['novaposhta' => [22.0, 22.0], 'rozetka' => [21.95, 22.0]], self::logoSizes($html));

        $stock = $this->render(self::method(), []);
        self::assertStringNotContainsString('fn_sviat_checkbox_carrier', $stock);
        self::assertStringNotContainsString('sviat__checkbox__receipt_box_note', $stock);
    }

    /** Логотипи однакові на око: висота намальованого 22px, viewBox обрізаний по фарбі, тож висота svg і є висота фарби. */
    public function testLogosHaveEqualInkHeight(): void
    {
        $rows = [];
        foreach (['novaposhta', 'ukrposhta', 'rozetka'] as $carrier) {
            $rows[] = ['carrier' => $carrier, 'switch' => 'x', 'on' => true, 'type' => 'CASH', 'label' => '', 'skip' => false];
        }

        self::assertSame(['novaposhta' => [22.0, 22.0], 'ukrposhta' => [23.24, 22.0], 'rozetka' => [21.95, 22.0]], self::logoSizes($this->render(self::method(), $rows)));
    }

    /** @return array<string, array{float, float}> перевізник → [ширина, висота] svg; viewBox має ті самі пропорції */
    private static function logoSizes(string $html): array
    {
        preg_match_all('~data-carrier-logo="(\w+)"[^>]*width="([\d.]+)" height="([\d.]+)" viewBox="[\d.\-]+ [\d.\-]+ ([\d.]+) ([\d.]+)"~', $html, $m, PREG_SET_ORDER);
        $sizes = [];
        foreach ($m as [, $carrier, $width, $height, $vbWidth, $vbHeight]) {
            self::assertEqualsWithDelta((float)$width / (float)$height, (float)$vbWidth / (float)$vbHeight, 0.01, $carrier . ': пропорції viewBox');
            $sizes[$carrier] = [(float)$width, (float)$height];
        }

        return $sizes;
    }

    private function extender(?string $stored, ?Settings $settings = null): BackendExtender
    {
        $payments = $this->createStub(PaymentsEntity::class);
        $payments->method('cols')->willReturnSelf();
        $payments->method('findOne')->willReturn((object)['id' => 18, Init::CARRIER_PAYMENTS_FIELD => $stored]);
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturn($payments);
        $settings ??= $this->settings();
        $request = new Request();
        $extender = new ReflectionClass(BackendExtender::class)->newInstanceWithoutConstructor();
        Closure::bind(function () use ($factory, $settings, $request) {
            $this->entityFactory = $factory;
            $this->settings = $settings;
            $this->request = $request;
        }, $extender, BackendExtender::class)();

        return $extender;
    }

    /** @param list<array<string, mixed>> $rows */
    private function render(object $method, array $rows): string
    {
        $this->compileDir ??= sys_get_temp_dir() . '/okay-checkbox-rows-' . bin2hex(random_bytes(4));
        if (!is_dir($this->compileDir)) {
            mkdir($this->compileDir);
        }
        // Сток 4.5.3 має Smarty 4 (клас \Smarty), форк — Smarty 5 (\Smarty\Smarty); потрібні методи однакові.
        $class = class_exists('Smarty\\Smarty') ? 'Smarty\\Smarty' : 'Smarty';
        $smarty = new $class();
        $smarty->setCompileDir($this->compileDir);
        $smarty->assign('btr', new class {
            public function __get(string $name): string
            {
                return $name;
            }

            public function __isset(string $name): bool
            {
                return true;
            }

            public function getTranslation(string $name): string
            {
                return $name;
            }
        });
        $smarty->assign('payment_method', $method);
        $smarty->assign('checkbox_carrier_rows', $rows);
        $smarty->assign('rootUrl', 'https://shop.test');

        return $smarty->fetch('string:' . file_get_contents(self::MODULE . '/Backend/design/html/fiscal_receipt_payment_type_block.tpl'));
    }
}
