<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Засоби платежу, які знає Checkbox.
 *
 * Форм оплати рівно дві: CASH (CashPaymentPayload) і CASHLESS
 * (CardPaymentPayload). Тип CARD у схемі теж є, але позначений DEPRECATED, а
 * будь-яке інше значення API відхиляє.
 *
 * Мітки безготівкових засобів — це рядок 19 чека («засіб оплати»), який наказ
 * Мінфіну № 601 від 22.11.2024 зробив обов'язковим для безготівкової форми і не
 * друкує для готівкової. Перелік зібраний з поля `label` схеми Checkbox
 * («Використовуйте наступні значення») та з рекомендацій самого Checkbox за
 * наказом № 601. Він закритий навмисне: вигадану мітку API прийме, а ДПС
 * отримає документ із засобом платежу, якого не існує, — Checkbox прямо
 * попереджає про контрольну перевірку і штраф.
 *
 * Код засобу (0 для готівки, 1 для безготівки) не передаємо: у CASH його в
 * схемі немає взагалі, у CASHLESS він і так дорівнює 1 за замовчуванням.
 *
 * Кутові дужки в мітці означають місце для назви платіжної системи. Такий засіб
 * не один на магазин: NovaPay, LiqPay і WayForPay — три різні записи одного
 * шаблону. Тому ключ джерела складений: `integrator:LiqPay`. Назва живе просто
 * в ключі, тож мітка чека виводиться з нього одного, без звірки з
 * налаштуваннями, — і ланцюжок, відкритий торік, лишається читабельним.
 */
final class CheckboxPaymentCatalogue
{
    const TYPE_CASH     = 'CASH';
    const TYPE_CASHLESS = 'CASHLESS';

    /** Розділювач між засобом платежу та назвою платіжної системи в ключі. */
    const NAME_SEPARATOR = ':';

    /** Обмеження поля label у Checkbox. */
    const LABEL_MAX_LENGTH = 128;

    /**
     * Ключ → [форма оплати, мітка за замовчуванням].
     *
     * Ключі — наші, не з API: у payload іде лише пара type + label. Порядок тут
     * задає порядок у налаштуваннях і в списку на картці замовлення.
     *
     * @var array<string, array{0: string, 1: string}>
     */
    private static $catalogue = [
        'cash'               => [self::TYPE_CASH,     'Готівка'],
        'bank_account'       => [self::TYPE_CASHLESS, 'З поточного рахунку'],
        'internet_banking'   => [self::TYPE_CASHLESS, 'Інтернет банкінг'],
        // Немає в переліку схеми API, але Checkbox рекомендує саме її для
        // переказу з картки клієнта на IBAN підприємця.
        'iban_details'       => [self::TYPE_CASHLESS, 'За реквізитами (IBAN)'],
        'internet_acquiring' => [self::TYPE_CASHLESS, 'Інтернет еквайринг'],
        'payment_card'       => [self::TYPE_CASHLESS, 'Картка'],
        'integrator'         => [self::TYPE_CASHLESS, 'Платіж через інтегратора <назва інтегратора>'],
        'nbpsp_transfer'     => [self::TYPE_CASHLESS, 'Переказ через ННПП'],
        'nbpsp_terminal'     => [self::TYPE_CASHLESS, 'Переказ через ПТКС ННПП'],
        'bank_terminal'      => [self::TYPE_CASHLESS, 'Переказ через ПТКС банку'],
        'gift_certificate'   => [self::TYPE_CASHLESS, 'Подарунковий сертифікат'],
        'voucher'            => [self::TYPE_CASHLESS, 'Талон'],
        'token'              => [self::TYPE_CASHLESS, 'Жетон'],
        'chip'               => [self::TYPE_CASHLESS, 'Фішка'],
        'money_substitute'   => [self::TYPE_CASHLESS, 'Електронний грошовий замінник'],
        'game_substitute'    => [self::TYPE_CASHLESS, 'Ігровий замінник гривні'],
        'emoney'             => [self::TYPE_CASHLESS, 'Електронні гроші <назва>'],
        'digital_money'      => [self::TYPE_CASHLESS, 'Цифрові гроші <назва>'],
        'crypto'             => [self::TYPE_CASHLESS, 'Криптовалюта <назва>'],
    ];

    /**
     * Що показувати, поки магазин не обрав нічого сам.
     *
     * Без інтегратора: його мітка потребує назви платіжної системи, а вгадати
     * її не можна.
     */
    const DEFAULT_ENABLED = ['cash', 'bank_account', 'internet_banking'];

    /**
     * Засоби зі сценаріїв, які Checkbox описує для інтернет-торгівлі: термінал,
     * інтегратор, IBAN, еквайринг. Решта каталогу — сертифікати, жетони,
     * замінники — у налаштуваннях згорнута: вісімнадцять рядків поспіль ховають
     * ті три, які магазину справді потрібні.
     */
    const COMMON = [
        'cash',
        'bank_account',
        'internet_banking',
        'iban_details',
        'internet_acquiring',
        'payment_card',
        'integrator',
    ];

    /** @return string[] базові ключі в порядку каталогу */
    public static function keys(): array
    {
        return array_keys(self::$catalogue);
    }

    /** Засіб платежу без назви платіжної системи. */
    public static function base(string $key): string
    {
        $position = strpos($key, self::NAME_SEPARATOR);

        return $position === false ? $key : substr($key, 0, $position);
    }

    /** Назва платіжної системи з ключа, або null якщо її там немає. */
    public static function name(string $key): ?string
    {
        $position = strpos($key, self::NAME_SEPARATOR);

        return $position === false ? null : substr($key, $position + 1);
    }

    public static function compose(string $base, string $name): string
    {
        return $base . self::NAME_SEPARATOR . trim($name);
    }

    /**
     * Ключ придатний для чека: засіб є в каталозі, і назва платіжної системи
     * присутня рівно там, де мітка лишила під неї місце.
     */
    public static function isKnown(string $key): bool
    {
        $base = self::base($key);
        if (!isset(self::$catalogue[$base])) {
            return false;
        }

        $name = self::name($key);
        if (!self::needsName($base)) {
            return $name === null;
        }

        // Порожня назва дала б «Платіж через інтегратора» без самого
        // інтегратора. Мітка формально чинна, але налаштування такого не
        // складуть — отже, дійти сюди воно могло лише в обхід сторінки.
        return $name !== null && trim($name) !== '' && self::labelIsValid(self::defaultLabel($key));
    }

    /** @throws \InvalidArgumentException */
    public static function type(string $key): string
    {
        $base = self::base($key);
        self::assertKnownBase($base);

        return self::$catalogue[$base][0];
    }

    /**
     * Мітка, яка піде в рядок 19 чека.
     *
     * Для складеного ключа підставляє назву платіжної системи на місце кутових
     * дужок. Для базового ключа шаблону віддає шаблон як є — такий ключ до чека
     * не доходить, його відсіює isKnown().
     *
     * @throws \InvalidArgumentException
     */
    public static function defaultLabel(string $key): string
    {
        $base = self::base($key);
        self::assertKnownBase($base);

        $template = self::$catalogue[$base][1];
        $name = self::name($key);
        if ($name === null || !preg_match('~<[^>]*>~u', $template, $placeholder)) {
            return $template;
        }

        // str_replace, а не preg_replace: у рядку заміни «$1» і «\1» — це
        // зворотні посилання, тож назва «A$1B» мовчки друкувалась би в чеку як
        // «AB». Магазин вписав одне, ДПС побачила б інше.
        return trim(str_replace($placeholder[0], trim($name), $template));
    }

    /** Мітка засобу без місця під назву — те, що друкується без підстановок. */
    public static function labelPrefix(string $base): string
    {
        return trim((string)preg_replace('~<[^>]*>~u', '', self::defaultLabel($base)));
    }

    /**
     * Чи потребує мітка назви платіжної системи від магазину.
     *
     * Невідома база — «ні», а не виняток: це питання, і на нього є відповідь.
     * Кидати звідси означало б класти кожну сторінку замовлення фаталом, щойно
     * в налаштуваннях опиниться зайвий ключ — байдуже, з підробленого POST чи
     * з правки значення руками.
     */
    public static function needsName(string $base): bool
    {
        if (!isset(self::$catalogue[$base])) {
            return false;
        }

        return strpos(self::$catalogue[$base][1], '<') !== false;
    }

    /**
     * Мітка з незаповненим шаблоном («Платіж через інтегратора <назва>») —
     * готовий рядок фіскального документа з кутовими дужками замість назви
     * платіжної системи. Checkbox таке приймає, ДПС бачить сміття.
     */
    public static function labelIsValid(string $label): bool
    {
        $label = trim($label);

        return $label !== ''
            && mb_strlen($label) <= self::LABEL_MAX_LENGTH
            && strpbrk($label, '<>') === false
            // Рядок 19 чека — один рядок. Перенос усередині розриває реквізит
            // навпіл, а нульовий байт узагалі не має де взятися в назві
            // платіжної системи.
            && preg_match('~[\x00-\x1F\x7F]~', $label) !== 1;
    }

    private static function assertKnownBase(string $base): void
    {
        if (!isset(self::$catalogue[$base])) {
            throw new \InvalidArgumentException('Невідоме джерело коштів: ' . $base);
        }
    }
}
