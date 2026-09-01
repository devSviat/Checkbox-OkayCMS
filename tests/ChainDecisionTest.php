<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxChainDecision;
use PHPUnit\Framework\TestCase;

/**
 * Розвилка автоматики: замовлення з відкритим ланцюжком мусить отримати чек
 * післяплати, а не другий чек продажу на повну суму.
 */
class ChainDecisionTest extends TestCase
{
    public function testOrderWithoutChainGetsOrdinarySale(): void
    {
        self::assertSame(
            CheckboxChainDecision::ACTION_SALE,
            CheckboxChainDecision::forOrder(null, false)
        );
    }

    public function testOrderWithSaleReceiptIsLeftAlone(): void
    {
        self::assertSame(
            CheckboxChainDecision::ACTION_NONE,
            CheckboxChainDecision::forOrder(null, true)
        );
    }

    public function testOpenChainGetsAfterPayment(): void
    {
        self::assertSame(
            CheckboxChainDecision::ACTION_AFTER_PAYMENT,
            CheckboxChainDecision::forOrder('PARTIAL_PAID', false)
        );
    }

    public function testClosedChainIsLeftAlone(): void
    {
        self::assertSame(
            CheckboxChainDecision::ACTION_NONE,
            CheckboxChainDecision::forOrder('FULL_PAID', false)
        );
    }

    /**
     * Повернений ланцюжок не повертає автоматику до звичайного продажу: рішення,
     * що робити з таким замовленням, лишається за менеджером.
     */
    public function testCancelledChainDoesNotFallBackToSale(): void
    {
        self::assertSame(
            CheckboxChainDecision::ACTION_NONE,
            CheckboxChainDecision::forOrder('CANCELLED', false)
        );
        self::assertSame(
            CheckboxChainDecision::ACTION_NONE,
            CheckboxChainDecision::forOrder('PARTIAL_CANCELLED', false)
        );
    }

    /**
     * Найдорожча помилка розвилки: недоступний Checkbox не має виглядати як
     * замовлення без ланцюжка, інакше на вже сплачений аванс поїде ще й повний
     * чек продажу — подвійна фіскалізація на живі гроші.
     */
    public function testUnknownChainStatusNeverFallsBackToSale(): void
    {
        self::assertSame(
            CheckboxChainDecision::ACTION_NONE,
            CheckboxChainDecision::forOrder(CheckboxChainDecision::STATUS_UNKNOWN, false)
        );
        self::assertSame(
            CheckboxChainDecision::ACTION_NONE,
            CheckboxChainDecision::forOrder(CheckboxChainDecision::STATUS_UNKNOWN, true)
        );
    }
}
