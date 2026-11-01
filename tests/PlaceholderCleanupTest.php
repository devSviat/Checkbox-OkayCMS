<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPlaceholderCleanup;
use PHPUnit\Framework\TestCase;

class PlaceholderCleanupTest extends TestCase
{
    private function row(int $id, int $orderId, string $type = 'sale', string $receiptId = ''): object
    {
        return (object)['id' => $id, 'order_id' => $orderId, 'receipt_type' => $type, 'receipt_id' => $receiptId];
    }

    public function testKeepsNewestPlaceholderOfOrder(): void
    {
        $surplus = CheckboxPlaceholderCleanup::surplusIds([$this->row(5, 1), $this->row(9, 1), $this->row(7, 1)]);

        sort($surplus);
        self::assertSame([5, 7], $surplus);
    }

    public function testSingleCopyIsNotSurplus(): void
    {
        self::assertSame([], CheckboxPlaceholderCleanup::surplusIds([$this->row(5, 1)]));
    }

    public function testSaleAndReturnAreCountedSeparately(): void
    {
        self::assertSame([], CheckboxPlaceholderCleanup::surplusIds([$this->row(5, 1), $this->row(6, 1, 'return')]));
    }

    public function testOrdersAreIndependent(): void
    {
        self::assertSame([], CheckboxPlaceholderCleanup::surplusIds([$this->row(5, 1), $this->row(6, 2)]));
    }

    public function testChainIntentsAreNeverSurplus(): void
    {
        self::assertSame([], CheckboxPlaceholderCleanup::surplusIds([
            $this->row(5, 1, 'prepayment'),
            $this->row(6, 1, 'prepayment'),
        ]));
    }

    public function testRealReceiptsAreNeverSurplus(): void
    {
        self::assertSame([], CheckboxPlaceholderCleanup::surplusIds([
            $this->row(5, 1, 'sale', 'real-receipt'),
            $this->row(6, 1),
        ]));
    }

    public function testMissingTypeCountsAsSale(): void
    {
        $older = $this->row(5, 1);
        $older->receipt_type = null;

        self::assertSame([5], CheckboxPlaceholderCleanup::surplusIds([$older, $this->row(6, 1)]));
    }
}
