<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Джерело коштів → форма та засіб оплати фіскального чека.
 *
 * `type` лягає в рядок 18 чека («ГОТІВКА»/«БЕЗГОТІВКОВА»), `label` — у рядок 19
 * («засіб оплати»), обов'язковий для безготівкової форми. Рядки взяті з
 * офіційних прикладів Checkbox і мінятись без потреби не мають.
 *
 * Розрізняти джерела обов'язково не лише через мітку: переказ з рахунку клієнта
 * через банк узагалі не потребує фіскалізації, а той самий платіж карткою —
 * потребує.
 */
final class CheckboxPaymentForm
{
    const SOURCE_BANK_ACCOUNT = 'bank_account';
    const SOURCE_CARD         = 'card';
    const SOURCE_NOVAPAY      = 'novapay';
    const SOURCE_CASH         = 'cash';

    /** @var array<string, array{0: string, 1: string}> */
    private static $map = [
        self::SOURCE_BANK_ACCOUNT => ['CASHLESS', 'З поточного рахунку'],
        self::SOURCE_CARD         => ['CASHLESS', 'Інтернет банкінг'],
        self::SOURCE_NOVAPAY      => ['CASHLESS', 'Платіж через інтегратора NovaPay'],
        self::SOURCE_CASH         => ['CASH', 'Готівка'],
    ];

    /** @return string[] */
    public static function sources(): array
    {
        return array_keys(self::$map);
    }

    public static function isKnown(string $source): bool
    {
        return isset(self::$map[$source]);
    }

    /**
     * Мітка, яка піде в рядок 19 чека для цього джерела.
     *
     * Потрібна інтерфейсу: менеджер обирає джерело, а друкується мітка, і без
     * неї фіскальний наслідок вибору лишається невидимим до самого чека.
     */
    public static function receiptLabel(string $source): string
    {
        if (!isset(self::$map[$source])) {
            throw new \InvalidArgumentException('Невідоме джерело коштів: ' . $source);
        }

        return self::$map[$source][1];
    }

    /**
     * @param string|null $labelOverride мітка зі способу оплати замовлення.
     *                                   Автоматичні чеки несуть саме її, щоб
     *                                   одне замовлення не дало податковій два
     *                                   різні засоби платежу. Тип лишається за
     *                                   джерелом: він визначає CASH/CASHLESS.
     * @return array{type: string, label: string, value: int}
     * @throws \InvalidArgumentException на невідомому джерелі
     */
    public static function payment(string $source, int $valueKopiyky, ?string $labelOverride = null): array
    {
        if (!isset(self::$map[$source])) {
            throw new \InvalidArgumentException('Невідоме джерело коштів: ' . $source);
        }

        list($type, $label) = self::$map[$source];
        if ($labelOverride !== null && trim($labelOverride) !== '') {
            $label = trim($labelOverride);
        }

        return ['type' => $type, 'label' => $label, 'value' => $valueKopiyky];
    }
}
