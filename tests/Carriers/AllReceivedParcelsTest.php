<?php

namespace Modules\Sviat\Checkbox\Carriers;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\Settings;
use Okay\Modules\Sviat\Checkbox\Carriers\AllReceivedParcels;
use Okay\Modules\Sviat\Checkbox\Carriers\NovaPoshtaReceived;
use Okay\Modules\Sviat\Checkbox\Carriers\ReceivedParcels;
use Okay\Modules\Sviat\Checkbox\Carriers\RozetkaReceived;
use Okay\Modules\Sviat\Checkbox\Carriers\UkrposhtaReceived;
use Okay\Modules\Sviat\NovaPoshta\Integration\NpOrders;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use Okay\Modules\Sviat\RozetkaDelivery\Integration\RozetkaOrders;
use Okay\Modules\Sviat\Ukrposhta\Integration\UpOrders;
use PHPUnit\Framework\TestCase;

if (!class_exists(NpOrders::class)) {
    require_once __DIR__ . '/../NpOrdersStub.php';
}
if (!class_exists(NovaPoshtaTrackingEntity::class)) {
    require_once __DIR__ . '/../NovaPoshtaTrackingEntityStub.php';
}
if (!class_exists(UpOrders::class)) {
    require_once __DIR__ . '/../UpOrdersStub.php';
}
if (!class_exists(RozetkaOrders::class)) {
    require_once __DIR__ . '/../RozetkaOrdersStub.php';
}

/** Стоковий OkayCMS без жодного перевізника не повинен ні падати, ні вмикати чек «при отриманні». */
class AllReceivedParcelsTest extends TestCase
{
    private static function facade(array $ids): object
    {
        return new class ($ids) {
            public function __construct(private array $ids)
            {
            }

            public function receivedSince(string $since): array
            {
                return $this->ids;
            }

            public function receivedCodSince(string $since): array
            {
                return $this->ids;
            }
        };
    }

    private function all(bool $npActive, bool $upActive, array $np = [], array $up = [], bool $rzActive = false, array $rz = []): ReceivedParcels
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn(string $vendor, string $name): bool => ($name === 'NovaPoshta' && $npActive) || ($name === 'Ukrposhta' && $upActive) || ($name === 'RozetkaDelivery' && $rzActive));
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(static fn(string $name): ?string => match ($name) {
            'sviat__rozetka__cod_fiscal' => 'shop',
            'sviat__rozetka__cod_fiscal_shop_since' => '2026-10-01 00:00:00',
            default => null,
        });
        $npFacade = self::facade($np);
        $upFacade = self::facade($up);
        $rzFacade = self::facade($rz);

        return new AllReceivedParcels(
            new NovaPoshtaReceived($modules, $this->createStub(EntityFactory::class), static fn(): object => $npFacade),
            new UkrposhtaReceived($modules, static fn(): object => $upFacade),
            new RozetkaReceived($modules, $settings, static fn(): object => $rzFacade),
        );
    }

    public function testUnionIsUnique(): void
    {
        $all = $this->all(true, true, [101, 102], [102, 301]);

        self::assertTrue($all->available());
        self::assertSame([101, 102, 301], $all->orderIds('2026-11-01 00:00:00'));
    }

    public function testOnlyUkrposhta(): void
    {
        $all = $this->all(false, true, [101], [301]);

        self::assertTrue($all->available());
        self::assertSame([301], $all->orderIds(null));
    }

    public function testStockHasNothing(): void
    {
        $all = $this->all(false, false, [101], [301]);

        self::assertFalse($all->available());
        self::assertSame([], $all->orderIds('2026-11-01 00:00:00'));
    }

    public function testUnionWithRozetka(): void
    {
        $all = $this->all(true, true, [101, 102], [301], true, [102, 501]);

        self::assertTrue($all->available());
        self::assertSame([101, 102, 301, 501], $all->orderIds('2026-11-01 00:00:00'));
    }

    public function testOnlyRozetka(): void
    {
        $all = $this->all(false, false, [101], [301], true, [501]);

        self::assertTrue($all->available());
        self::assertSame([501], $all->orderIds(null));
    }
}
