<?php

namespace Modules\Sviat\Checkbox\Carriers;

use Okay\Core\Modules\Modules;
use Okay\Modules\Sviat\Checkbox\Carriers\UkrposhtaReceived;
use Okay\Modules\Sviat\Ukrposhta\Integration\UpOrders;
use PHPUnit\Framework\TestCase;

if (!class_exists(UpOrders::class)) {
    require_once __DIR__ . '/../UpOrdersStub.php';
}

/** Чек «при отриманні» йде й на посилки Укрпошти, але лише коли її модуль увімкнений. */
class UkrposhtaReceivedTest extends TestCase
{
    /** @var list<string> */
    private array $asked = [];

    private function modules(bool $ukrposhta): Modules
    {
        $modules = $this->createStub(Modules::class);
        $modules->method('isActiveModule')->willReturnCallback(static fn(string $vendor, string $name): bool => $name === 'Ukrposhta' && $ukrposhta);

        return $modules;
    }

    private function source(bool $active, array $ids = []): UkrposhtaReceived
    {
        $asked = &$this->asked;
        $facade = new class ($ids, $asked) {
            public function __construct(private array $ids, private array &$asked)
            {
            }

            public function receivedSince(string $since): array
            {
                $this->asked[] = $since;

                return $this->ids;
            }
        };

        return new UkrposhtaReceived($this->modules($active), static fn(): object => $facade);
    }

    public function testPassesSinceToFacade(): void
    {
        $source = $this->source(true, [301, '302']);

        self::assertTrue($source->available());
        self::assertSame([301, 302], $source->orderIds('2026-11-01 00:00:00'));
        self::assertSame(['2026-11-01 00:00:00'], $this->asked);
    }

    public function testWithoutInstallDateAsksFromTheBeginning(): void
    {
        $this->source(true, [301])->orderIds(null);

        self::assertSame(['1970-01-01 00:00:00'], $this->asked);
    }

    public function testUkrposhtaDisabledLeavesNpOnly(): void
    {
        $source = new UkrposhtaReceived($this->modules(false), static fn(): object => throw new \LogicException('фасад вимкненого модуля не будується'));

        self::assertFalse($source->available());
        self::assertSame([], $source->orderIds('2026-11-01 00:00:00'));
    }
}
