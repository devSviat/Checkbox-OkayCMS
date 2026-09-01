<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentForm;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Мітка засобу оплати — це рядок 19 фіскального чека за Положенням № 13, а не
 * вільний текст. Значення взяті з офіційних прикладів Checkbox; будь-яка
 * "покращена" редакція тут означає неправильний реквізит у податковому документі.
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
            'з картки клієнта на IBAN'  => [CheckboxPaymentForm::SOURCE_CARD, 'CASHLESS', 'Інтернет банкінг'],
            'NovaPay при отриманні'     => [CheckboxPaymentForm::SOURCE_NOVAPAY, 'CASHLESS', 'Платіж через інтегратора NovaPay'],
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
            'Платіж LiqPay'
        );

        self::assertSame('CASHLESS', $payment['type'], 'тип лишається за джерелом');
        self::assertSame('Платіж LiqPay', $payment['label']);
        self::assertSame(150000, $payment['value']);
    }

    /** Порожня мітка в налаштуваннях не має витирати стандартну. */
    public function testEmptyOverrideFallsBackToTheStandardLabel(): void
    {
        foreach ([null, '', '   '] as $empty) {
            $payment = CheckboxPaymentForm::payment(CheckboxPaymentForm::SOURCE_CARD, 100, $empty);
            self::assertSame('Інтернет банкінг', $payment['label']);
        }
    }

    /**
     * Інтерфейс показує менеджеру, що саме надрукується в чеку. Мітка мусить
     * збігатись із тією, що реально піде в payments[] — розходження тут
     * означало б, що вибір показують один, а фіскалізують інший.
     */
    /** @dataProvider sourceProvider */
    #[DataProvider('sourceProvider')]
    public function testReceiptLabelMatchesWhatGoesIntoThePayment(string $source, string $type, string $label): void
    {
        self::assertSame($label, CheckboxPaymentForm::receiptLabel($source));
        self::assertSame(
            CheckboxPaymentForm::payment($source, 100)['label'],
            CheckboxPaymentForm::receiptLabel($source)
        );
    }

    /**
     * Нове джерело без підпису в мовних файлах з'явиться в списку порожнім
     * рядком, і менеджер обере невідомо що. Дефект тихий: побачити його можна
     * лише в готовому чеку.
     */
    public function testEverySourceHasALabelInEveryLanguage(): void
    {
        foreach (['ua', 'ru', 'en'] as $language) {
            $lang = [];
            require __DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox/Backend/lang/' . $language . '.php';

            foreach (CheckboxPaymentForm::sources() as $source) {
                $key = 'sviat__checkbox__source_' . $source;
                self::assertArrayHasKey($key, $lang, "{$language}: джерело {$source} без підпису");
                self::assertNotSame('', trim((string)$lang[$key]), "{$language}: порожній підпис для {$source}");
            }
        }
    }

    public function testUnknownSourceIsRejectedRatherThanGuessed(): void
    {
        // Мовчазний фолбек тут дав би неправдивий реквізит у фіскальному чеку
        $this->expectException(\InvalidArgumentException::class);
        CheckboxPaymentForm::payment('щось_невідоме', 50000);
    }

    public function testOtherIsNotAValidType(): void
    {
        // Checkbox приймає лише CASH і CASHLESS — OTHER дає 422
        foreach (CheckboxPaymentForm::sources() as $source) {
            self::assertContains(CheckboxPaymentForm::payment($source, 100)['type'], ['CASH', 'CASHLESS']);
        }
    }
}
