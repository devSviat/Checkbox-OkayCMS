<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPrepaymentRules;
use PHPUnit\Framework\TestCase;

/**
 * Checkbox відхиляє чек передплати, сума якого дорівнює вартості товарів:
 * «Сума платежів чека не може дорівнювати сумі всіх товарів». Ловимо це до
 * мережевого виклику, щоб менеджер бачив зрозумілу помилку, а не 400 з API.
 */
class PrepaymentRulesTest extends TestCase
{
    public function testAdvanceSmallerThanGoodsIsValid(): void
    {
        self::assertTrue(CheckboxPrepaymentRules::advanceIsValid(50000, 200000));
    }

    public function testAdvanceEqualToGoodsIsRejected(): void
    {
        self::assertFalse(CheckboxPrepaymentRules::advanceIsValid(200000, 200000));
    }

    public function testAdvanceLargerThanGoodsIsRejected(): void
    {
        self::assertFalse(CheckboxPrepaymentRules::advanceIsValid(250000, 200000));
    }

    public function testZeroAndNegativeAreRejected(): void
    {
        self::assertFalse(CheckboxPrepaymentRules::advanceIsValid(0, 200000));
        self::assertFalse(CheckboxPrepaymentRules::advanceIsValid(-100, 200000));
    }
}
