<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxCardActions;
use PHPUnit\Framework\TestCase;

/**
 * Кнопки на картці мусять збігатися з тим, що дозволяє сервер. Кнопка, яку
 * сервер відхилить, — глухий кут; прихована кнопка там, де дія законна, змушує
 * менеджера шукати обхід. Обидві помилки вже траплялися.
 */
class CardActionsTest extends TestCase
{
    /** Замовлення без жодного чека: доступні обидва початкові шляхи. */
    public function testFreshOrderOffersBothPaths(): void
    {
        $actions = CheckboxCardActions::forOrder(null, false, false, false);

        self::assertTrue($actions['prepayment']);
        self::assertTrue($actions['sale']);
        self::assertFalse($actions['afterPayment']);
        self::assertFalse($actions['returnChain']);
        self::assertFalse($actions['return']);
    }

    /** Відкритий ланцюжок тримає замовлення: повний чек продажу неможливий. */
    public function testOpenChainBlocksSaleAndSecondAdvance(): void
    {
        $actions = CheckboxCardActions::forOrder('PARTIAL_PAID', false, false, false);

        self::assertFalse($actions['prepayment'], 'другий ланцюжок поверх відкритого');
        self::assertFalse($actions['sale']);
        self::assertTrue($actions['afterPayment']);
        self::assertTrue($actions['returnChain']);
    }

    public function testFullyPaidChainOnlyOffersReturn(): void
    {
        $actions = CheckboxCardActions::forOrder('FULL_PAID', false, false, false);

        self::assertFalse($actions['prepayment']);
        self::assertFalse($actions['sale']);
        self::assertFalse($actions['afterPayment'], 'борг уже закрито');
        self::assertTrue($actions['returnChain']);
    }

    /**
     * Після повернення ланцюжка замовлення повертається до звичайного шляху —
     * і до авансу теж. Сервер (createPrepaymentReceipt) це дозволяє явно, а
     * картка ховала кнопку: менеджер лишався тільки з повним чеком продажу.
     *
     * @dataProvider cancelledStatusProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('cancelledStatusProvider')]
    public function testCancelledChainReopensBothPaths(string $status): void
    {
        $actions = CheckboxCardActions::forOrder($status, false, false, false);

        self::assertTrue($actions['prepayment'], 'аванс після повернення');
        self::assertTrue($actions['sale']);
        self::assertFalse($actions['afterPayment']);
        self::assertFalse($actions['returnChain'], 'повертати вже нічого');
    }

    /** @return array<string, array{0: string}> */
    public static function cancelledStatusProvider(): array
    {
        return [
            'повністю скасований' => ['CANCELLED'],
            'частково скасований' => ['PARTIAL_CANCELLED'],
        ];
    }

    /**
     * Невідомий стан — це «Checkbox не відповів». Діяти наосліп на замовленні з
     * ланцюжком дорожче, ніж почекати оновлення стану.
     */
    public function testUnknownChainStateBlocksEverythingButReturnOfTheChain(): void
    {
        $actions = CheckboxCardActions::forOrder('unknown', false, false, false);

        self::assertFalse($actions['prepayment']);
        self::assertFalse($actions['sale']);
        self::assertFalse($actions['afterPayment']);
        self::assertFalse($actions['returnChain']);
    }

    /** Виставлений чек продажу закриває обидва шляхи наперед. */
    public function testSaleReceiptClosesBothCreationPaths(): void
    {
        $actions = CheckboxCardActions::forOrder(null, true, true, false);

        self::assertFalse($actions['prepayment']);
        self::assertFalse($actions['sale']);
        self::assertTrue($actions['return'], 'є що повертати');
    }

    /**
     * Повернений чек продажу прибирає кнопку повернення — але не повертає
     * кнопку продажу: другий чек продажу сервер не випустить навіть тепер.
     */
    public function testReturnedSaleHidesBothSaleAndReturn(): void
    {
        $actions = CheckboxCardActions::forOrder(null, true, false, false);

        self::assertFalse($actions['return'], 'повертати вже нічого');
        self::assertFalse($actions['sale'], 'сервер відхилить другий чек продажу');
        self::assertFalse($actions['prepayment']);
    }

    /**
     * «Не відправляти чек» стосується основної суми, тобто способу оплати
     * замовлення. Аванс може надійти зовсім іншим шляхом, і менеджер називає
     * його явно — прапорець його не гасить.
     */
    public function testPaymentSkipHidesSaleButNotAdvance(): void
    {
        $actions = CheckboxCardActions::forOrder(null, false, false, true);

        self::assertFalse($actions['sale']);
        self::assertFalse($actions['return']);
        self::assertTrue($actions['prepayment']);
    }

    public function testPaymentSkipDoesNotBlockClosingAnOpenChain(): void
    {
        $actions = CheckboxCardActions::forOrder('PARTIAL_PAID', false, false, true);

        self::assertTrue($actions['afterPayment']);
        self::assertTrue($actions['returnChain']);
    }

    /**
     * Аванс — це сума, строго менша за товари. Якщо замовлення вже позначене
     * оплаченим повністю, «частини, що лишилась» не існує, і кнопка веде в
     * глухий кут — сервер (CheckboxPrepaymentRules) все одно її відхилить.
     */
    public function testFullyPaidOrderHidesPrepaymentButKeepsSale(): void
    {
        $actions = CheckboxCardActions::forOrder(null, false, false, false, true);

        self::assertFalse($actions['prepayment'], 'нічого не лишилось довносити авансом');
        self::assertTrue($actions['sale'], 'повний чек продажу лишається доступним');
    }

    /**
     * Пояснення на картці мусить з'являтись лише тоді, коли аванс зняв саме
     * прапорець оплати. Інакше воно суперечило б списку чеків над ним.
     */
    public function testPaidFlagIsNamedAsTheReasonOnlyWhenItIsTheReason(): void
    {
        $paid = CheckboxCardActions::forOrder(null, false, false, false, true);
        self::assertTrue($paid['prepaymentHiddenByPaid']);

        $bySale = CheckboxCardActions::forOrder(null, true, false, false, true);
        self::assertFalse($bySale['prepaymentHiddenByPaid'], 'причина — чек продажу, і його видно у списку');

        $byChain = CheckboxCardActions::forOrder('PARTIAL_PAID', false, false, false, true);
        self::assertFalse($byChain['prepaymentHiddenByPaid'], 'причина — живий ланцюжок');

        $offered = CheckboxCardActions::forOrder(null, false, false, false, false);
        self::assertFalse($offered['prepaymentHiddenByPaid'], 'аванс і так пропонується');
    }

    /**
     * «Не відправляти» ховає sale через спосіб оплати. Оплачене замовлення
     * додатково ховає й аванс — разом не лишається жодної дії, і це коректно:
     * фіскалізувати тут нема чим.
     */
    public function testFullyPaidOrderWithSkippedPaymentMethodOffersNothing(): void
    {
        $actions = CheckboxCardActions::forOrder(null, false, false, true, true);

        self::assertFalse($actions['prepayment']);
        self::assertFalse($actions['sale']);
    }
}
