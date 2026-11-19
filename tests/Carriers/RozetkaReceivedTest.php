<?php

namespace Modules\Sviat\Checkbox\Carriers;

use Okay\Core\Modules\Modules;
use Okay\Core\Settings;
use Okay\Modules\Sviat\Checkbox\Carriers\RozetkaReceived;
use Okay\Modules\Sviat\RozetkaDelivery\Integration\RozetkaOrders;
use PHPUnit\Framework\TestCase;

if (!class_exists(RozetkaOrders::class)) {
    require_once __DIR__ . '/../RozetkaOrdersStub.php';
}

/**
 * Чек «при отриманні» на післяплату ROZETKA виставляє магазин лише тоді, коли так обрано в модулі доставки.
 * Інакше чек може видати хтось інший, а два чеки на ті самі гроші — так само погано, як жодного.
 */
class RozetkaReceivedTest extends TestCase
{
    /** @var list<string> */
    private array $asked = [];
    private function modules(bool $active): Modules
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn(string $vendor, string $name): bool => $vendor === 'Sviat' && $name === 'RozetkaDelivery' && $active);

        return $modules;
    }

    private function settings(mixed $fiscal, ?string $shopSince = '2026-10-01 00:00:00'): Settings
    {
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(static fn(string $name): mixed => match ($name) {
            'sviat__rozetka__cod_fiscal' => $fiscal,
            'sviat__rozetka__cod_fiscal_shop_since' => $shopSince,
            default => null,
        });

        return $settings;
    }

    private function source(bool $active, mixed $fiscal, array $ids = [], ?string $shopSince = '2026-10-01 00:00:00'): RozetkaReceived
    {
        $asked = &$this->asked;
        $facade = new class ($ids, $asked) {
            public function __construct(private array $ids, private array &$asked)
            {
            }

            public function receivedCodSince(string $since): array
            {
                $this->asked[] = $since;

                return $this->ids;
            }
        };

        return new RozetkaReceived($this->modules($active), $this->settings($fiscal, $shopSince), static fn(): object => $facade);
    }

    private static function untouchable(): \Closure
    {
        return static fn(): object => throw new \LogicException('фасад не питають, коли чек видає не магазин');
    }

    public function testUnknownOrProviderGivesNothing(): void
    {
        foreach (['unknown', 'provider', '', null, 'SHOP', ['shop']] as $fiscal) {
            $source = new RozetkaReceived($this->modules(true), $this->settings($fiscal), self::untouchable());

            self::assertFalse($source->available(), var_export($fiscal, true));
            self::assertSame([], $source->orderIds('2026-11-01 00:00:00'), var_export($fiscal, true));
        }
    }

    public function testShopPassesSince(): void
    {
        $source = $this->source(true, 'shop', [501, '502', 501]);

        self::assertTrue($source->available());
        self::assertSame([501, 502], $source->orderIds('2026-11-01 00:00:00'));
        self::assertSame(['2026-11-01 00:00:00'], $this->asked);
    }

    public function testSettingWithSpacesStillShop(): void
    {
        self::assertTrue($this->source(true, " shop\n")->available());
    }

    public function testWithoutInstallDateAsksFromTheSwitch(): void
    {
        $this->source(true, 'shop', [501])->orderIds(null);

        self::assertSame(['2026-10-01 00:00:00'], $this->asked);
    }

    /** Посилки, видані до переходу на «магазин», міг фіскалізувати хтось інший. */
    public function testParcelsBeforeTheSwitchAreNotAsked(): void
    {
        $this->source(true, 'shop', [501], '2026-11-10 08:00:00')->orderIds('2026-11-01 00:00:00');

        self::assertSame(['2026-11-10 08:00:00'], $this->asked);
    }

    /** «Магазин», записаний повз сторінку налаштувань: дати переходу немає, і чеків заднім числом теж. */
    public function testShopWithoutSwitchDateGivesNothing(): void
    {
        foreach ([null, '', 'вчора'] as $shopSince) {
            $source = new RozetkaReceived($this->modules(true), $this->settings('shop', $shopSince), self::untouchable());

            self::assertSame([], $source->orderIds('2026-11-01 00:00:00'), var_export($shopSince, true));
        }
    }

    public function testDisabledModuleGivesNothing(): void
    {
        $source = new RozetkaReceived($this->modules(false), $this->settings('shop'), self::untouchable());

        self::assertFalse($source->available());
        self::assertSame([], $source->orderIds('2026-11-01 00:00:00'));
    }
}
