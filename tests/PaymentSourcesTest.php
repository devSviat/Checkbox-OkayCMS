<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentCatalogue;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentSources;
use PHPUnit\Framework\TestCase;

/**
 * Набір джерел коштів, який магазин складає в налаштуваннях, доходить до
 * фіскального чека без жодної перевірки на боці Checkbox: API приймає будь-яку
 * мітку. Тому весь захист живе тут.
 */
class PaymentSourcesTest extends TestCase
{
    public function testUnsetSettingGivesTheDefaultSet(): void
    {
        $config = CheckboxPaymentSources::decode(null);

        self::assertSame(CheckboxPaymentCatalogue::DEFAULT_ENABLED, $config['enabled']);
    }

    /** Зіпсований рядок у налаштуваннях не має класти картку замовлення. */
    public function testBrokenJsonFallsBackToDefaults(): void
    {
        foreach (['', 'не json', '[1,2,3]', '{"enabled":"cash"}'] as $broken) {
            $config = CheckboxPaymentSources::decode($broken);
            self::assertSame(CheckboxPaymentCatalogue::DEFAULT_ENABLED, $config['enabled'], $broken);
        }
    }

    public function testEnabledSetSurvivesEncodeDecode(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(['cash', 'payment_card']));

        self::assertSame(['cash', 'payment_card'], $config['enabled']);
    }

    /** Порядок скрізь один — каталогу, а не той, у якому адмін клікав. */
    public function testOrderFollowsTheCatalogue(): void
    {
        $config = CheckboxPaymentSources::decode(
            CheckboxPaymentSources::encode(['payment_card', 'cash', 'bank_account'])
        );

        self::assertSame(['cash', 'bank_account', 'payment_card'], $config['enabled']);
    }

    public function testUnknownKeysAreDropped(): void
    {
        $config = CheckboxPaymentSources::decode(
            CheckboxPaymentSources::encode(['cash', 'вигадане', 'вигадане:LiqPay'])
        );

        self::assertSame(['cash'], $config['enabled']);
    }

    /**
     * Інтеграторів у магазина стільки, скільки він завів: NovaPay і LiqPay —
     * два різні джерела, і кожне зі своєю міткою в рядку 19 чека.
     */
    public function testSeveralPaymentSystemsShareOneTemplate(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['cash', 'integrator:NovaPay', 'integrator:LiqPay', 'integrator:WayForPay'],
            ['integrator' => ['NovaPay', 'LiqPay', 'WayForPay']]
        ));

        self::assertSame(
            ['cash', 'integrator:NovaPay', 'integrator:LiqPay', 'integrator:WayForPay'],
            $config['enabled']
        );

        self::assertSame(
            [
                'Готівка',
                'Платіж через інтегратора NovaPay',
                'Платіж через інтегратора LiqPay',
                'Платіж через інтегратора WayForPay',
            ],
            array_column(CheckboxPaymentSources::visible($config), 'label')
        );
    }

    /** Порядок назв усередині одного засобу — той, у якому їх вписали. */
    public function testNamesKeepTheOrderTheyWereEnteredIn(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['integrator:LiqPay', 'integrator:NovaPay'],
            ['integrator' => ['LiqPay', 'NovaPay']]
        ));

        self::assertSame(['integrator:LiqPay', 'integrator:NovaPay'], $config['enabled']);
    }

    public function testDuplicateNamesAreCollapsed(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['integrator:NovaPay', 'integrator:NovaPay'],
            ['integrator' => ['NovaPay', 'NovaPay']]
        ));

        self::assertSame(['integrator:NovaPay'], $config['enabled']);
    }

    /**
     * Засіб із місцем під назву, але без самої назви, до списку не потрапляє:
     * менеджер обрав би його, і кутові дужки надрукувались би в рядку 19 чека.
     */
    public function testTemplateWithoutANameIsNotOffered(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(['cash', 'integrator']));

        self::assertSame(['cash'], $config['enabled']);
    }

    /** Назва не може сама містити кутові дужки — це знову зіпсований реквізит. */
    public function testNameWithAngleBracketsIsRejected(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['cash', 'integrator:<script>'],
            ['integrator' => ['<script>']]
        ));

        self::assertSame(['cash'], $config['enabled']);
    }

    public function testOverlongLabelIsRejected(): void
    {
        $long = str_repeat('а', CheckboxPaymentCatalogue::LABEL_MAX_LENGTH);
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['cash', 'integrator:' . $long],
            ['integrator' => [$long]]
        ));

        self::assertSame(['cash'], $config['enabled']);
    }

    /**
     * Мітка — один рядок фіскального документа. Перенос розриває реквізит
     * навпіл, а керівні байти в назві платіжної системи не мають де взятися.
     *
     * @dataProvider controlCharacterProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('controlCharacterProvider')]
    public function testControlCharactersAreRejected(string $name): void
    {
        self::assertFalse(CheckboxPaymentCatalogue::labelIsValid('Платіж через інтегратора ' . $name));
        self::assertFalse(CheckboxPaymentCatalogue::isKnown(CheckboxPaymentCatalogue::compose('integrator', $name)));

        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['cash', CheckboxPaymentCatalogue::compose('integrator', $name)],
            ['integrator' => [$name]]
        ));
        self::assertSame(['cash'], $config['enabled']);
        self::assertSame([], $config['names']);
    }

    /** @return array<string, array{0: string}> */
    public static function controlCharacterProvider(): array
    {
        return [
            'перенос рядка'   => ["Novo\nPay"],
            'повернення каретки' => ["Novo\rPay"],
            'табуляція'       => ["Novo\tPay"],
            'нульовий байт'   => ["Novo\x00Pay"],
            'вертикальна табуляція' => ["Novo\x0BPay"],
        ];
    }

    /**
     * Зайвий ключ у налаштуваннях не має класти сторінку.
     *
     * Розбір крутиться на кожному рендері картки замовлення, тож виняток звідси
     * означав би 500 на всіх замовленнях одразу — через одне зіпсоване значення
     * в налаштуваннях, підроблений POST або правку руками.
     *
     * @dataProvider hostileConfigProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('hostileConfigProvider')]
    public function testHostileConfigIsSurvived(string $raw): void
    {
        $config = CheckboxPaymentSources::decode($raw);

        self::assertNotEmpty($config['enabled']);
        foreach ($config['enabled'] as $key) {
            self::assertTrue(CheckboxPaymentCatalogue::isKnown($key), $key);
        }
        foreach (array_keys($config['names']) as $base) {
            self::assertTrue(CheckboxPaymentCatalogue::needsName($base), $base);
        }
    }

    /** @return array<string, array{0: string}> */
    public static function hostileConfigProvider(): array
    {
        return [
            'невідома база в names' => ['{"enabled":["cash"],"names":{"вигадане":["X"]}}'],
            'names не масив'        => ['{"enabled":["cash"],"names":{"integrator":"NovaPay"}}'],
            'назва не рядок'        => ['{"enabled":["cash"],"names":{"integrator":[123,null,{}]}}'],
            'enabled не рядки'      => ['{"enabled":[1,true,null],"names":{}}'],
            'фіксована база в names' => ['{"enabled":["cash"],"names":{"cash":["Готівочка"]}}'],
            'глибоко вкладене'      => ['{"enabled":["cash"],"names":{"integrator":[["NovaPay"]]}}'],
        ];
    }

    /** Те саме, але з форми: невідома база в POST не має давати 500. */
    public function testHostileFormInputIsSurvived(): void
    {
        $raw = CheckboxPaymentSources::encode(['cash'], ['вигадане' => ['X'], 'cash' => ['Y']]);
        $config = CheckboxPaymentSources::decode($raw);

        self::assertSame(['cash'], $config['enabled']);
        self::assertSame([], $config['names']);
    }

    /**
     * Порожній набір — не конфігурація, а глухий кут: менеджер не проведе аванс
     * і не закриє вже відкритий ланцюжок.
     */
    public function testEmptySelectionFallsBackToDefaults(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode([]));

        self::assertSame(CheckboxPaymentCatalogue::DEFAULT_ENABLED, $config['enabled']);
    }

    /**
     * Мітки без місця під назву задає наказ № 601, і в налаштуваннях їх немає
     * взагалі — зберігаються самі ключі. Переписати «Готівку» нічим.
     */
    public function testFixedLabelsComeFromTheCatalogueOnly(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(['cash', 'bank_account']));

        self::assertSame(
            ['Готівка', 'З поточного рахунку'],
            array_column(CheckboxPaymentSources::visible($config), 'label')
        );
    }

    /** Правити можна рівно ті засоби, де каталог лишив місце під назву. */
    public function testOnlyTemplateRowsAreEditable(): void
    {
        $editable = [];
        foreach (CheckboxPaymentSources::rows(CheckboxPaymentSources::decode(null)) as $row) {
            if ($row['editable']) {
                $editable[] = $row['key'];
            }
        }

        self::assertSame(['integrator', 'emoney', 'digital_money', 'crypto'], $editable);
    }

    /** Сторінка налаштувань показує весь каталог, а не лише обране. */
    public function testRowsCoverTheWholeCatalogue(): void
    {
        $rows = CheckboxPaymentSources::rows(CheckboxPaymentSources::decode(null));

        self::assertSame(CheckboxPaymentCatalogue::keys(), array_column($rows, 'key'));
        foreach ($rows as $row) {
            self::assertNotSame('', $row['prefix'], $row['key']);
            self::assertContains($row['type'], ['CASH', 'CASHLESS'], $row['key']);
        }
    }

    /** Заведені назви повертаються в той рядок, до якого належать. */
    public function testRowsCarryTheConfiguredNames(): void
    {
        $rows = CheckboxPaymentSources::rows(CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['integrator:NovaPay', 'integrator:LiqPay', 'crypto:USDT'],
            ['integrator' => ['NovaPay', 'LiqPay'], 'crypto' => ['USDT']]
        )));
        $byKey = array_column($rows, null, 'key');

        self::assertSame(['NovaPay', 'LiqPay'], array_column($byKey['integrator']['names'], 'name'));
        self::assertSame([true, true], array_column($byKey['integrator']['names'], 'on'));
        self::assertSame(['USDT'], array_column($byKey['crypto']['names'], 'name'));
        self::assertSame([], $byKey['emoney']['names']);
        self::assertSame('Платіж через інтегратора', $byKey['integrator']['prefix']);
    }

    /**
     * Вимкнена платіжна система лишається записаною. Інакше «вимкнути» означало
     * б «стерти», і повернути її можна було б лише набравши назву заново.
     */
    public function testDisabledPaymentSystemKeepsItsName(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['cash', 'integrator:NovaPay'],
            ['integrator' => ['NovaPay', 'LiqPay']]
        ));

        self::assertSame(['NovaPay', 'LiqPay'], $config['names']['integrator']);
        self::assertSame(['cash', 'integrator:NovaPay'], $config['enabled'], 'LiqPay вимкнено');

        $byKey = array_column(CheckboxPaymentSources::rows($config), null, 'key');
        self::assertSame(
            [['name' => 'NovaPay', 'on' => true], ['name' => 'LiqPay', 'on' => false]],
            $byKey['integrator']['names']
        );
    }

    /**
     * Увімкнене без запису — розходження між тим, що бачить менеджер, і тим, що
     * видно в налаштуваннях. Виграє те, що видно.
     */
    public function testEnabledKeyWithoutAStoredNameIsDropped(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['cash', 'integrator:LiqPay'],
            ['integrator' => ['NovaPay']]
        ));

        self::assertSame(['cash'], $config['enabled']);
    }

    /** Вимкнена система лишається на видноті — інакше запис є, а знайти ніде. */
    public function testDisabledRareTemplateStaysVisible(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['cash'],
            ['crypto' => ['USDT']]
        ));
        $byKey = array_column(CheckboxPaymentSources::rows($config), null, 'key');

        self::assertTrue($byKey['crypto']['common']);
        self::assertFalse($byKey['crypto']['enabled']);
        self::assertFalse($byKey['emoney']['common']);
    }

    /** Заведене не ховається під «рідковживані», інакше знайти його ніде. */
    public function testConfiguredRareSourceStaysVisible(): void
    {
        $rows = CheckboxPaymentSources::rows(
            CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(['cash', 'token']))
        );
        $byKey = array_column($rows, null, 'key');

        self::assertTrue($byKey['token']['common'], 'увімкнений жетон має лишатись на видноті');
        self::assertFalse($byKey['chip']['common']);
    }

    /** Кожен пункт списку на картці замовлення придатний для чека. */
    public function testVisibleRowsAreReceiptReady(): void
    {
        $config = CheckboxPaymentSources::decode(CheckboxPaymentSources::encode(
            ['cash', 'bank_account', 'integrator:NovaPay'],
            ['integrator' => ['NovaPay']]
        ));

        foreach (CheckboxPaymentSources::visible($config) as $row) {
            self::assertTrue(CheckboxPaymentCatalogue::isKnown($row['key']), $row['key']);
            self::assertTrue(CheckboxPaymentCatalogue::labelIsValid($row['label']), $row['key']);
        }
    }
}
