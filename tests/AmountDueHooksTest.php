<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Init\Init;
use Okay\Modules\Sviat\NovaPoshta\Waybills\AmountDue as NpAmountDue;
use Okay\Modules\Sviat\Ukrposhta\Shipments\AmountDue as UpAmountDue;
use PHPUnit\Framework\TestCase;

/**
 * Аванс зменшує післяплату в накладних обох перевізників. На стоку цих модулів немає,
 * а реєстрація розширення на відсутній метод кидає виняток, тож хук є лише для наявного класу.
 */
class AmountDueHooksTest extends TestCase
{
    public function testBothCarriersHooked(): void
    {
        $hooks = Init::amountDueHooks();

        foreach ([NpAmountDue::class, UpAmountDue::class] as $class) {
            if (method_exists($class, 'onPickup')) {
                self::assertContains(['class' => $class, 'method' => 'onPickup'], $hooks);
            } else {
                self::assertNotContains(['class' => $class, 'method' => 'onPickup'], $hooks);
            }
        }
    }

    public function testOnlyExistingMethodsHooked(): void
    {
        foreach (Init::amountDueHooks() as $hook) {
            self::assertTrue(method_exists($hook['class'], $hook['method']), $hook['class']);
        }
    }

    public function testUkrposhtaHookedInThisEngine(): void
    {
        if (!class_exists(UpAmountDue::class)) {
            self::markTestSkipped('Sviat/Ukrposhta is not in this engine');
        }

        self::assertContains(['class' => UpAmountDue::class, 'method' => 'onPickup'], Init::amountDueHooks());
    }
}
