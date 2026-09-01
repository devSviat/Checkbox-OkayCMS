<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxChainId;
use PHPUnit\Framework\TestCase;

/**
 * Checkbox вимагає від custom_relation_id унікальності в межах організації і
 * довжини від 10 символів. Порушення довжини — 422 і невиставлений чек.
 */
class ChainIdTest extends TestCase
{
    public function testIdContainsOrderNumber(): void
    {
        self::assertSame('prepayment-1234', CheckboxChainId::build(1234));
    }

    public function testShortestPossibleIdStillMeetsApiMinimum(): void
    {
        // Найкоротший можливий випадок — замовлення №1
        self::assertGreaterThanOrEqual(10, strlen(CheckboxChainId::build(1)));
    }

    public function testRepeatedChainForSameOrderGetsSuffix(): void
    {
        self::assertSame('prepayment-1234-2', CheckboxChainId::build(1234, 1));
        self::assertSame('prepayment-1234-3', CheckboxChainId::build(1234, 2));
    }
}
