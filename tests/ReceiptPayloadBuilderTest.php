<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptPayloadBuilder as Builder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Позиції фіскального чека. Це та арифметика, розбіжність у якій виявляється не
 * в логах, а в акті звірки з ДПС: чек пробивається на суму, яку порахував цей
 * код, а гроші приходять на суму із замовлення.
 *
 * Дві речі тут неочевидні й тому покриті найщільніше: переведення в копійки
 * через подвійне округлення (просте (int) з'їдає копійку на half-cent) і
 * пропорційне розмазування знижки по позиціях.
 */
class ReceiptPayloadBuilderTest extends TestCase
{
    /** @return object позиція замовлення у тому вигляді, як її бачить білдер */
    private static function purchase(
        float $price,
        int $amount = 1,
        int $variantId = 1,
        string $name = 'Пилосос',
        array $taxes = []
    ): object {
        return (object) [
            'price'           => $price,
            'amount'          => $amount,
            'variant_id'      => $variantId,
            'fullProductName' => $name,
            'taxes'           => $taxes,
        ];
    }

    // ------------------------------------------------------------------
    // Копійки
    // ------------------------------------------------------------------

    /**
     * Ціни в чеку — цілі копійки. Найтонше місце — half-cent: у double
     * 1.005 × 100 дорівнює 100.4999…, тож просте (int) дало б 100 замість 101.
     */
    /** @dataProvider kopiykyProvider */
    #[DataProvider('kopiykyProvider')]
    public function testHryvniaAreConvertedToWholeKopiyky(float $hryvnia, int $expected): void
    {
        self::assertSame($expected, Builder::toKopiyky($hryvnia));
    }

    public static function kopiykyProvider(): array
    {
        return [
            'ціле число'          => [100.0, 10000],
            'дві копійки'         => [0.02, 2],
            'звичайна ціна'       => [1234.56, 123456],
            'half-cent угору'     => [1.005, 101],
            'half-cent, більший'  => [1005.005, 100501],
            'нуль'                => [0.0, 0],
            'третина гривні'      => [0.333, 33],
            'майже гривня'        => [0.999, 100],
        ];
    }

    /**
     * Саме те, заради чого стоїть проміжне round(..., 6): без нього
     * (int)(1.005 * 100) дає 100 — на копійку менше.
     */
    public function testNaiveCastWouldLoseTheKopiyka(): void
    {
        self::assertSame(100, (int) (1.005 * 100), 'контроль: наївне приведення справді губить копійку');
        self::assertSame(101, Builder::toKopiyky(1.005));
    }

    // ------------------------------------------------------------------
    // Кількість
    // ------------------------------------------------------------------

    /** Checkbox приймає кількість у тисячних долях одиниці. */
    public function testQuantityIsExpressedInThousandths(): void
    {
        $payload = Builder::build([self::purchase(10.0, 3)], 30.0, 30.0);

        self::assertSame(3000, $payload['goods'][0]['quantity']);
    }

    // ------------------------------------------------------------------
    // Без знижки
    // ------------------------------------------------------------------

    public function testWithoutADiscountPricesGoThroughUnchanged(): void
    {
        $payload = Builder::build(
            [self::purchase(100.0, 2), self::purchase(50.5, 1)],
            250.5,
            250.5
        );

        self::assertSame(10000, $payload['goods'][0]['good']['price']);
        self::assertSame(5050, $payload['goods'][1]['good']['price']);
        self::assertSame(25050, $payload['paymentValue']);
    }

    /** Сума оплати рахується з ПЕРЕРАХОВАНИХ цін, а не з переданого підсумку. */
    public function testPaymentValueIsDerivedFromTheLineItems(): void
    {
        $payload = Builder::build([self::purchase(33.33, 3)], 99.99, 99.99);

        self::assertSame(9999, $payload['paymentValue']);
    }

    // ------------------------------------------------------------------
    // Знижка
    // ------------------------------------------------------------------

    /**
     * Знижка не окремим рядком, а розмазана по позиціях пропорційно: чек не має
     * рядка «знижка», тож зменшується ціна кожного товару.
     */
    public function testDiscountIsSpreadProportionallyAcrossItems(): void
    {
        // 10% знижки: 200 → 180
        $payload = Builder::build(
            [self::purchase(100.0, 1), self::purchase(100.0, 1)],
            200.0,
            180.0
        );

        self::assertSame(9000, $payload['goods'][0]['good']['price']);
        self::assertSame(9000, $payload['goods'][1]['good']['price']);
        self::assertSame(18000, $payload['paymentValue']);
    }

    /** Пропорція зберігається й для позицій різної ціни. */
    public function testDiscountKeepsTheRatioBetweenDifferentlyPricedItems(): void
    {
        // 25% знижки: 400 → 300
        $payload = Builder::build(
            [self::purchase(300.0, 1), self::purchase(100.0, 1)],
            400.0,
            300.0
        );

        self::assertSame(22500, $payload['goods'][0]['good']['price']);
        self::assertSame(7500, $payload['goods'][1]['good']['price']);
        self::assertSame(30000, $payload['paymentValue']);
    }

    /** Знижка враховує кількість: перерахована ціна множиться на неї. */
    public function testDiscountAppliesPerUnitNotPerLine(): void
    {
        // 50% знижки: 200 → 100
        $payload = Builder::build([self::purchase(100.0, 2)], 200.0, 100.0);

        self::assertSame(5000, $payload['goods'][0]['good']['price']);
        self::assertSame(10000, $payload['paymentValue']);
    }

    /**
     * Націнка (сума до сплати більша за початкову) знижкою не вважається — ціни
     * лишаються як є. Гілка спрацьовує лише коли є що віднімати.
     */
    public function testTotalAboveTheUndiscountedSumDoesNotTouchPrices(): void
    {
        $payload = Builder::build([self::purchase(100.0, 1)], 100.0, 120.0);

        self::assertSame(10000, $payload['goods'][0]['good']['price']);
    }

    /** Нульова початкова сума не має давати ділення на нуль. */
    public function testZeroUndiscountedTotalIsSafe(): void
    {
        $payload = Builder::build([self::purchase(100.0, 1)], 0.0, 100.0);

        self::assertSame(10000, $payload['goods'][0]['good']['price']);
    }

    /**
     * Мутація на місці: перерахована ціна записується назад у позицію
     * замовлення. Код нижче за течією читає саме її, тож поведінку зафіксовано
     * навмисно — «полагодивши» це на value-семантику, зламаєш запис чека в базу.
     */
    public function testDiscountedPriceIsWrittenBackIntoThePurchase(): void
    {
        $purchase = self::purchase(100.0, 1);

        Builder::build([$purchase], 200.0, 180.0);

        self::assertSame(90.0, $purchase->price);
    }

    public function testPriceIsNotMutatedWhenThereIsNoDiscount(): void
    {
        $purchase = self::purchase(100.0, 1);

        Builder::build([$purchase], 100.0, 100.0);

        self::assertSame(100.0, $purchase->price);
    }

    // ------------------------------------------------------------------
    // Склад позиції
    // ------------------------------------------------------------------

    public function testGoodCarriesTheVariantIdAsCodeAndTheFullName(): void
    {
        $payload = Builder::build([self::purchase(10.0, 1, 42, 'Пилосос - Синій')], 10.0, 10.0);

        self::assertSame('42', $payload['goods'][0]['good']['code']);
        self::assertSame('Пилосос - Синій', $payload['goods'][0]['good']['name']);
    }

    /** Назва збирається з товару й варіанта, коли готової повної назви немає. */
    public function testNameFallsBackToProductPlusVariant(): void
    {
        $purchase = (object) [
            'price' => 10.0, 'amount' => 1, 'variant_id' => 1,
            'product_name' => 'Пилосос', 'variant_name' => 'Синій',
        ];

        $payload = Builder::build([$purchase], 10.0, 10.0);

        self::assertSame('Пилосос - Синій', $payload['goods'][0]['good']['name']);
    }

    public function testNameOmitsTheVariantWhenThereIsNone(): void
    {
        $purchase = (object) [
            'price' => 10.0, 'amount' => 1, 'variant_id' => 1,
            'product_name' => 'Пилосос', 'variant_name' => '',
        ];

        self::assertSame('Пилосос', Builder::build([$purchase], 10.0, 10.0)['goods'][0]['good']['name']);
    }

    public function testTaxCodesArePassedThroughAndDefaultToAnEmptyList(): void
    {
        $withTax = Builder::build([self::purchase(10.0, 1, 1, 'Т', ['A'])], 10.0, 10.0);
        self::assertSame(['A'], $withTax['goods'][0]['good']['tax']);

        $purchase = (object) ['price' => 10.0, 'amount' => 1, 'variant_id' => 1, 'fullProductName' => 'Т'];
        self::assertSame([], Builder::build([$purchase], 10.0, 10.0)['goods'][0]['good']['tax']);
    }

    /** Нечислові значення з бази не мають ламати чек — вони обнуляються. */
    public function testNonNumericFieldsDegradeToZero(): void
    {
        $purchase = (object) [
            'price' => 'не число', 'amount' => null, 'variant_id' => 'abc', 'fullProductName' => 'Т',
        ];

        $good = Builder::build([$purchase], 0.0, 0.0)['goods'][0];

        self::assertSame(0, $good['good']['price']);
        self::assertSame(0, $good['quantity']);
        self::assertSame('0', $good['good']['code']);
    }

    // ------------------------------------------------------------------
    // Сходження двох сум
    // ------------------------------------------------------------------

    /**
     * Checkbox звіряє суму оплати із сумою позицій і відхиляє чек, у якому
     * вони різні. Розходяться вони саме на округленні: ціна позиції — цілі
     * копійки, і підсумок мусить збиратися з них, а не з вихідних цін.
     */
    /** @dataProvider roundingProvider */
    #[DataProvider('roundingProvider')]
    public function testPaymentValueEqualsTheSumOfLines(array $purchases, float $undiscounted, float $total): void
    {
        $payload = Builder::build($purchases, $undiscounted, $total);

        $lines = 0;
        foreach ($payload['goods'] as $good) {
            $lines += $good['good']['price'] * intdiv($good['quantity'], 1000);
        }

        self::assertSame($lines, $payload['paymentValue']);
    }

    public static function roundingProvider(): array
    {
        return [
            'знижка 1 грн на 3 шт по 100'   => [[self::purchase(100.0, 3)], 300.0, 299.0],
            'знижка 1 грн на 7 шт по 10'    => [[self::purchase(10.0, 7)], 70.0, 69.0],
            'знижка 33% на 6 шт по 49.90'   => [[self::purchase(49.90, 6)], 299.40, 200.60],
            'дві позиції різної ціни'       => [[self::purchase(300.0), self::purchase(99.99)], 399.99, 350.0],
            'три знаки в ціні, без знижки'  => [[self::purchase(149.991, 2)], 299.982, 299.982],
        ];
    }

    // ------------------------------------------------------------------
    // Чек повернення
    // ------------------------------------------------------------------

    public function testReturnReceiptMarksEveryLine(): void
    {
        $payload = Builder::build([self::purchase(10.0), self::purchase(20.0)], 30.0, 30.0, isReturn: true);

        self::assertTrue($payload['goods'][0]['is_return']);
        self::assertTrue($payload['goods'][1]['is_return']);
    }

    /** Звичайний чек не має нести цей прапорець узагалі. */
    public function testSaleReceiptHasNoReturnFlag(): void
    {
        $payload = Builder::build([self::purchase(10.0)], 10.0, 10.0);

        self::assertArrayNotHasKey('is_return', $payload['goods'][0]);
    }

    public function testEmptyPurchaseListGivesAnEmptyReceipt(): void
    {
        $payload = Builder::build([], 0.0, 0.0);

        self::assertSame([], $payload['goods']);
        self::assertSame(0, $payload['paymentValue']);
    }

    /**
     * Назва в чеку мусить бути українською й непорожньою.
     *
     * Знімок назви в ok_purchases збережено мовою покупця — там є російські
     * рядки. Він має бути останнім засобом, а не спрацьовувати щоразу, коли
     * жива назва виявилась порожньою: порожня конкатенація дає рядок, а не
     * null, тож оператор ?? її не ловить.
     */
    public function testEmptyLiveNameDoesNotReachTheReceipt(): void
    {
        $purchase = (object)[
            'product_id' => 1, 'variant_id' => 1, 'amount' => 1,
            'price' => 100.0, 'undiscounted_price' => 100.0,
            'fullProductName' => '',
            'product_name' => 'Стеклянная полка', 'variant_name' => '',
        ];

        $payload = Builder::build([$purchase], 100.0, 100.0, false);

        self::assertSame(
            'Стеклянная полка',
            $payload['goods'][0]['good']['name'],
            'порожня жива назва мусить поступитись знімку, а не потрапити в чек'
        );
    }

    /** Непорожня жива назва завжди сильніша за знімок. */
    public function testLiveNameWinsOverTheSnapshot(): void
    {
        $purchase = (object)[
            'product_id' => 1, 'variant_id' => 1, 'amount' => 1,
            'price' => 100.0, 'undiscounted_price' => 100.0,
            'fullProductName' => 'Скляна полиця',
            'product_name' => 'Стеклянная полка', 'variant_name' => '',
        ];

        $payload = Builder::build([$purchase], 100.0, 100.0, false);

        self::assertSame('Скляна полиця', $payload['goods'][0]['good']['name']);
    }
}
