<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptSet;
use PHPUnit\Framework\TestCase;

/**
 * «Чи має замовлення чек продажу» — питання, хибна відповідь на яке коштує
 * другого фіскального чека на ті самі гроші.
 */
class ReceiptSetTest extends TestCase
{
    public function testSaleWithReceiptIdCounts(): void
    {
        self::assertTrue(CheckboxReceiptSet::hasSaleReceipt([
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => 'abc'],
        ]));
    }

    /** Заготовка при закритій зміні ще не чек: receipt_id порожній. */
    public function testPlaceholderDoesNotCount(): void
    {
        self::assertFalse(CheckboxReceiptSet::hasSaleReceipt([
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => ''],
        ]));
    }

    public function testOtherTypesDoNotCount(): void
    {
        foreach ([FiscalReceiptsEntity::TYPE_RETURN, FiscalReceiptsEntity::TYPE_PREPAYMENT, FiscalReceiptsEntity::TYPE_AFTER_PAYMENT] as $type) {
            self::assertFalse(
                CheckboxReceiptSet::hasSaleReceipt([(object)['receipt_type' => $type, 'receipt_id' => 'abc']]),
                "тип {$type} не є чеком продажу"
            );
        }
    }

    /**
     * Найважливіший тест файлу: вікно між викладкою файлів і виконанням
     * міграції на проді.
     *
     * Поки колонки receipt_type немає, вибірка з фільтром по ній падає, а
     * помилки SQL тут ковтаються мовчки — назовні йде порожній результат.
     * «Чека продажу немає» означає «виставити ще один», тобто дубль на живі
     * гроші. Запис без цієї властивості мусить рахуватись продажем — рівно так,
     * як поводився модуль до появи колонки.
     */
    public function testReceiptWithoutTypeCountsAsSale(): void
    {
        self::assertTrue(CheckboxReceiptSet::hasSaleReceipt([
            (object)['receipt_id' => 'abc'],
        ]));
    }

    public function testEmptySetHasNoSale(): void
    {
        self::assertFalse(CheckboxReceiptSet::hasSaleReceipt([]));
    }

    /**
     * Кнопки на картці питають інше, ніж автоматика.
     *
     * Автоматика питає «чи фіскалізували колись» — повторно виставляти чек їй
     * не можна. Менеджеру ж після повного повернення продаж треба дозволити,
     * інакше замовлення не перефіскалізувати з картки взагалі. А головне —
     * кнопка повернення мусить зникнути, коли повертати вже нічого: інакше
     * другий чек повернення на той самий продаж робиться одним кліком.
     */
    public function testFullyReturnedSaleIsNoLongerCovered(): void
    {
        $receipts = [
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => 's1', 'related_receipt_id' => null],
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_RETURN, 'receipt_id' => 'r1', 'related_receipt_id' => 's1'],
        ];

        self::assertTrue(CheckboxReceiptSet::hasSaleReceipt($receipts), 'для автоматики продаж лишається');
        self::assertFalse(CheckboxReceiptSet::hasUncoveredSaleReceipt($receipts), 'повертати вже нічого');
    }

    public function testSaleWithoutReturnIsUncovered(): void
    {
        self::assertTrue(CheckboxReceiptSet::hasUncoveredSaleReceipt([
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => 's1', 'related_receipt_id' => null],
        ]));
    }

    /** Друга пара продаж/повернення не має «покриватись» чужим поверненням. */
    public function testSecondSaleIsUncoveredWhileOnlyTheFirstWasReturned(): void
    {
        self::assertTrue(CheckboxReceiptSet::hasUncoveredSaleReceipt([
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => 's1', 'related_receipt_id' => null],
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_RETURN, 'receipt_id' => 'r1', 'related_receipt_id' => 's1'],
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => 's2', 'related_receipt_id' => null],
        ]));
    }

    /** Заготовка повернення без receipt_id ще нічого не покриває. */
    public function testReturnPlaceholderCoversNothing(): void
    {
        self::assertTrue(CheckboxReceiptSet::hasUncoveredSaleReceipt([
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => 's1', 'related_receipt_id' => null],
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_RETURN, 'receipt_id' => '', 'related_receipt_id' => 's1'],
        ]));
    }

    public function testAnyOneSaleAmongOthersIsEnough(): void
    {
        self::assertTrue(CheckboxReceiptSet::hasSaleReceipt([
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_RETURN, 'receipt_id' => 'r1'],
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => 's1'],
        ]));
    }

    /**
     * «Незавершені чеки» — це заготовки продажу й повернення, які чекають на
     * відкриту зміну. Рядок-намір ланцюжка теж має порожній receipt_id, але
     * чекає він не на зміну, а на відповідь Checkbox.
     *
     * Різниця не косметична: попередження про незавершені чеки ховає на картці
     * всі дії, включно з кнопкою оновлення стану. Порахувати намір ланцюжка
     * разом із заготовками означало б замкнути менеджера після першого ж
     * таймауту — саме в тій ситуації, заради якої намір і пишеться.
     */
    public function testChainIntentIsNotAnUnfinishedReceipt(): void
    {
        self::assertSame(0, CheckboxReceiptSet::countUnfinished([
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_PREPAYMENT, 'receipt_id' => ''],
        ]));
    }

    public function testPlaceholdersAreCounted(): void
    {
        self::assertSame(2, CheckboxReceiptSet::countUnfinished([
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => ''],
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_RETURN, 'receipt_id' => ''],
            (object)['receipt_type' => FiscalReceiptsEntity::TYPE_SALE, 'receipt_id' => 'issued'],
        ]));
    }

    /** До міграції типу немає — запис мусить рахуватись як продаж, тобто як раніше. */
    public function testRowWithoutTypeCountsAsPlaceholder(): void
    {
        self::assertSame(1, CheckboxReceiptSet::countUnfinished([
            (object)['receipt_id' => ''],
        ]));
    }
}
