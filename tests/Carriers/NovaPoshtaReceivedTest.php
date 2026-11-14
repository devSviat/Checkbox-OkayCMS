<?php

namespace Modules\Sviat\Checkbox\Carriers;

use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Modules\Sviat\Checkbox\Carriers\NovaPoshtaReceived;
use Okay\Modules\Sviat\NovaPoshta\Integration\NpOrders;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;
use PHPUnit\Framework\TestCase;

// Сусідні модулі необов'язкові: без них класів немає, а перевірки доступності питають саме про клас.
if (!class_exists(NpOrders::class)) {
    require_once __DIR__ . '/../NpOrdersStub.php';
}
if (!class_exists(NovaPoshtaTrackingEntity::class)) {
    require_once __DIR__ . '/../NovaPoshtaTrackingEntityStub.php';
}

/**
 * Чек «при отриманні» йде на посилки обох модулів Нової Пошти. Нового — за датою статусу НП,
 * бо перенесення переписує час запису для давно отриманих посилок.
 */
class NovaPoshtaReceivedTest extends TestCase
{
    /** @var list<string> */
    private array $asked = [];

    private function source(bool $new, bool $old, array $newIds = [], array $oldRows = []): NovaPoshtaReceived
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn(string $vendor, string $name): bool => ($name === 'NovaPoshta' && $new) || ($name === 'NovaPoshtaTracking' && $old));

        $oldEntity = $this->createStub(NovaPoshtaTrackingEntity::class);
        $oldEntity->method('find')->willReturn($oldRows);
        $entityFactory = $this->createStub(EntityFactory::class);
        $entityFactory->method('get')->willReturn($oldEntity);

        $asked = &$this->asked;
        $facade = new class ($newIds, $asked) {
            public function __construct(private array $ids, private array &$asked)
            {
            }

            public function receivedSince(string $since): array
            {
                $this->asked[] = $since;

                return $this->ids;
            }
        };

        return new NovaPoshtaReceived($modules, $entityFactory, static fn(): object => $facade);
    }

    private static function oldRow(int $orderId, string $updatedAt): object
    {
        return (object)['order_id' => $orderId, 'status_code' => '9', 'updated_at' => $updatedAt];
    }

    public function testNewModuleOnly(): void
    {
        $source = $this->source(true, false, [101, 102]);

        self::assertTrue($source->available());
        self::assertSame([101, 102], $source->orderIds('2026-11-01 00:00:00'));
        self::assertSame(['2026-11-01 00:00:00'], $this->asked);
    }

    public function testOldModuleOnlyKeepsItsRule(): void
    {
        $source = $this->source(false, true, [], [self::oldRow(201, '2026-10-01 00:00:00'), self::oldRow(202, '2026-11-02 00:00:00')]);

        self::assertTrue($source->available());
        self::assertSame([202], $source->orderIds('2026-11-01 00:00:00'));
        self::assertSame([], $this->asked, 'the new module is not asked while disabled');
    }

    public function testBothModulesWithoutRepeats(): void
    {
        $source = $this->source(true, true, [101, 202], [self::oldRow(202, '2026-11-02 00:00:00'), self::oldRow(203, '2026-11-03 00:00:00')]);

        self::assertSame([101, 202, 203], $source->orderIds('2026-11-01 00:00:00'));
    }

    public function testNeitherModule(): void
    {
        $source = $this->source(false, false, [101], [self::oldRow(201, '2026-11-02 00:00:00')]);

        self::assertFalse($source->available());
        self::assertSame([], $source->orderIds('2026-11-01 00:00:00'));
    }

    public function testWithoutInstallDate(): void
    {
        $source = $this->source(true, true, [101], [self::oldRow(201, '2020-01-01 00:00:00')]);

        self::assertSame([101, 201], $source->orderIds(null), 'the old module had no date filter without it');
        self::assertSame(['1970-01-01 00:00:00'], $this->asked);
    }
}
