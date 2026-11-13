<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Стан відправки чеків на телефон у Checkbox: чи ввімкнено і скільки на неї лишилось.
 *
 * Checkbox списує кожне повідомлення з цього балансу. Без автосписання порожній
 * баланс мовчки зупиняє відправку.
 */
final class CheckboxSmsBalance
{
    /** 50 ₴: близько 40 SMS, тобто кілька днів, щоб встигнути поповнити. */
    public const int LOW_KOPIYKY = 5000;

    public const string WARNING_OFF = 'off';
    public const string WARNING_LOW = 'low';

    public function __construct(
        public readonly bool $enabled,
        public readonly int $balanceKopiyky,
    ) {
    }

    /**
     * Відповідь `GET /organization/sms-billing`. `balance` — у копійках, гроші разом
     * із вартістю куплених SMS: кабінет показав 0,44 ₴ і 300 SMS по 1,26 ₴, API — 37844.
     */
    public static function fromApi(array $response): ?self
    {
        $enabled = $response['can_send_sms'] ?? null;
        $balance = $response['balance'] ?? null;
        if (!is_bool($enabled) || !is_int($balance)) {
            return null;
        }

        return new self($enabled, $balance);
    }

    /** @return self::WARNING_*|null */
    public function warning(): ?string
    {
        if (!$this->enabled) {
            return self::WARNING_OFF;
        }

        return $this->balanceKopiyky < self::LOW_KOPIYKY ? self::WARNING_LOW : null;
    }

    public function hryvnias(): string
    {
        return number_format($this->balanceKopiyky / 100, 2, ',', ' ');
    }
}
