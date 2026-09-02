<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentCatalogue;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Мітка засобу оплати — це рядок 19 фіскального чека за наказом Мінфіну № 601,
 * а не вільний текст. Значення взяті з переліку Checkbox; будь-яка "покращена"
 * редакція тут означає неправильний реквізит у податковому документі.
 */
class PaymentFormTest extends TestCase
{
    /** @dataProvider sourceProvider */
    #[DataProvider('sourceProvider')]
    public function testSourceMapsToExactApiValues(string $source, string $type, string $label): void
    {
        $payment = CheckboxPaymentForm::payment($source, 50000);

        self::assertSame($type, $payment['type']);
        self::assertSame($label, $payment['label']);
        self::assertSame(50000, $payment['value']);
    }

    public static function sourceProvider(): array
    {
        return [
            'переказ з рахунку клієнта' => [CheckboxPaymentForm::SOURCE_BANK_ACCOUNT, 'CASHLESS', 'З поточного рахунку'],
            'з картки клієнта на IBAN'  => ['internet_banking', 'CASHLESS', 'Інтернет банкінг'],
            'через термінал'            => ['payment_card', 'CASHLESS', 'Картка'],
            'готівка'                   => [CheckboxPaymentForm::SOURCE_CASH, 'CASH', 'Готівка'],
        ];
    }

    /**
     * Автоматичний чек післяплати мусить нести ту саму мітку, що й звичайний
     * чек продажу цього замовлення, — інакше одне замовлення дасть податковій
     * два різні засоби платежу. Мітку задає продавець у налаштуваннях способу
     * оплати, і саме вона тут перебиває стандартну.
     */
    public function testConfiguredLabelOverridesTheStandardOne(): void
    {
        $payment = CheckboxPaymentForm::payment(
            CheckboxPaymentForm::SOURCE_BANK_ACCOUNT,
            150000,
            'Платіж через інтегратора LiqPay'
        );

        self::assertSame('CASHLESS', $payment['type'], 'тип лишається за джерелом');
        self::assertSame('Платіж через інтегратора LiqPay', $payment['label']);
        self::assertSame(150000, $payment['value']);
    }

    /** Порожня мітка в налаштуваннях не має витирати стандартну. */
    public function testEmptyOverrideFallsBackToTheStandardLabel(): void
    {
        foreach ([null, '', '   '] as $empty) {
            $payment = CheckboxPaymentForm::payment('internet_banking', 100, $empty);
            self::assertSame('Інтернет банкінг', $payment['label']);
        }
    }

    /**
     * Мітка з місцем під назву («Платіж через інтегратора <назва інтегратора>»)
     * без самої назви — готовий рядок фіскального чека з кутовими дужками.
     * Checkbox таке приймає, тож упіймати це можна лише тут.
     */
    public function testUnfilledLabelTemplateNeverReachesAReceipt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CheckboxPaymentForm::payment(CheckboxPaymentForm::SOURCE_INTEGRATOR, 100);
    }

    /**
     * Назва платіжної системи живе в самому ключі, тож мітка чека виводиться з
     * нього одного — без звірки з налаштуваннями. Інакше ланцюжок, відкритий до
     * того, як магазин перебрав список, лишився б без свого засобу платежу.
     */
    public function testNamedIntegratorComposesItsOwnLabel(): void
    {
        foreach (['NovaPay', 'LiqPay', 'WayForPay'] as $system) {
            $payment = CheckboxPaymentForm::payment(
                CheckboxPaymentCatalogue::compose(CheckboxPaymentForm::SOURCE_INTEGRATOR, $system),
                100
            );

            self::assertSame('Платіж через інтегратора ' . $system, $payment['label']);
            self::assertSame('CASHLESS', $payment['type']);
        }
    }

    public function testEachTemplateComposesItsOwnLabel(): void
    {
        self::assertSame(
            'Криптовалюта USDT',
            CheckboxPaymentForm::payment('crypto:USDT', 100)['label']
        );
        self::assertSame(
            'Електронні гроші GooglePay',
            CheckboxPaymentForm::payment('emoney:GooglePay', 100)['label']
        );
    }

    /**
     * Назва підставляється дослівно. preg_replace трактував би «$1» і «\\1» як
     * зворотні посилання, тож магазин вписав би «A$1B», а в рядок 19 чека пішло
     * б «AB» — мовчки й лише в готовому фіскальному документі.
     *
     * @dataProvider trickyNameProvider
     */
    #[DataProvider('trickyNameProvider')]
    public function testNameIsSubstitutedLiterally(string $name): void
    {
        self::assertSame(
            'Платіж через інтегратора ' . $name,
            CheckboxPaymentForm::payment(CheckboxPaymentCatalogue::compose('integrator', $name), 100)['label']
        );
    }

    /** @return array<string, array{0: string}> */
    public static function trickyNameProvider(): array
    {
        return [
            'зворотне посилання $1' => ['A$1B'],
            'ціла збіжність $0'     => ['$0'],
            'зворотний слеш'        => ['Mono\\Pay'],
            'долар у кінці'         => ['100$'],
            'слеш із цифрою'        => ['Pay\\1'],
            'двокрапка в назві'     => ['Some:Thing'],
        ];
    }

    /** Порожня назва — «Платіж через інтегратора» без самого інтегратора. */
    public function testEmptyNameIsNotAValidSource(): void
    {
        self::assertFalse(CheckboxPaymentCatalogue::isKnown('integrator:'));
        self::assertFalse(CheckboxPaymentCatalogue::isKnown('integrator:   '));

        $this->expectException(\InvalidArgumentException::class);
        CheckboxPaymentForm::payment('integrator:', 100);
    }

    /** Назва там, де каталог її не передбачив, — це вже інший засіб платежу. */
    public function testNameOnAFixedSourceIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        CheckboxPaymentForm::payment('cash:Готівочка', 100);
    }

    public function testUnknownSourceIsRejectedRatherThanGuessed(): void
    {
        // Мовчазний фолбек тут дав би неправдивий реквізит у фіскальному чеку
        $this->expectException(\InvalidArgumentException::class);
        CheckboxPaymentForm::payment('щось_невідоме', 50000);
    }

    /**
     * Джерела, які називає сам код: автоматика виводить їх зі способу оплати
     * замовлення, тож перейменування в каталозі поклало б чек післяплати.
     * SOURCE_INTEGRATOR — база: сам по собі до чека він не доходить, лише разом
     * із назвою платіжної системи.
     */
    public function testSourcesNamedInCodeExistInTheCatalogue(): void
    {
        foreach ([
            CheckboxPaymentForm::SOURCE_BANK_ACCOUNT,
            CheckboxPaymentForm::SOURCE_INTEGRATOR,
            CheckboxPaymentForm::SOURCE_CASH,
        ] as $source) {
            self::assertContains($source, CheckboxPaymentCatalogue::keys(), $source);
        }

        self::assertTrue(CheckboxPaymentCatalogue::isKnown(CheckboxPaymentForm::SOURCE_CASH));
        self::assertTrue(CheckboxPaymentCatalogue::isKnown(CheckboxPaymentForm::SOURCE_BANK_ACCOUNT));
        self::assertFalse(
            CheckboxPaymentCatalogue::isKnown(CheckboxPaymentForm::SOURCE_INTEGRATOR),
            'інтегратор без назви — не джерело'
        );
    }

    public function testOnlyCashAndCashlessAreEverSent(): void
    {
        // Checkbox приймає лише CASH і CASHLESS — OTHER дає 422, CARD застарілий
        foreach (CheckboxPaymentCatalogue::keys() as $source) {
            self::assertContains(CheckboxPaymentCatalogue::type($source), ['CASH', 'CASHLESS'], $source);
        }
    }
}
