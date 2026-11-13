<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Куди Checkbox надішле чек після фіскалізації: блок `delivery` у запиті чека.
 *
 * Значення налаштування збігаються з попереднім модулем OkCMS (там 2–4 були
 * закоментовані), тож збережене раніше «1» і далі означає «на email».
 */
final class CheckboxReceiptDelivery
{
    public const int NONE = 0;
    public const int EMAIL = 1;
    public const int PHONE = 2;
    public const int EMAIL_AND_PHONE = 3;
    public const int EMAIL_ELSE_PHONE = 4;

    public const array MODES = [self::NONE, self::EMAIL, self::PHONE, self::EMAIL_AND_PHONE, self::EMAIL_ELSE_PHONE];

    public static function mode(mixed $setting): int
    {
        $mode = is_numeric($setting) ? (int)$setting : self::NONE;

        return in_array($mode, self::MODES, true) ? $mode : self::NONE;
    }

    public static function usesPhone(int $mode): bool
    {
        return in_array($mode, [self::PHONE, self::EMAIL_AND_PHONE, self::EMAIL_ELSE_PHONE], true);
    }

    /**
     * @return array{emails?: list<string>, phone?: string}|null null — нікуди не надсилати
     */
    public static function payload(int $mode, ?string $email, ?string $phone): ?array
    {
        $email = self::email($email);
        $phone = self::phone($phone);

        $sendEmail = in_array($mode, [self::EMAIL, self::EMAIL_AND_PHONE, self::EMAIL_ELSE_PHONE], true) && $email !== null;
        $sendPhone = $phone !== null && match ($mode) {
            self::PHONE, self::EMAIL_AND_PHONE => true,
            self::EMAIL_ELSE_PHONE => $email === null,
            default => false,
        };

        // `emails`, а не одиничне `email`: друге Checkbox позначив застарілим.
        $delivery = [];
        if ($sendEmail) {
            $delivery['emails'] = [$email];
        }
        if ($sendPhone) {
            $delivery['phone'] = $phone;
        }

        return $delivery === [] ? null : $delivery;
    }

    /**
     * Номер у вигляді 380XXXXXXXXX. Іноземний номер дає null: Checkbox приймає
     * лише українські, а `/receipts/{id}/sms` ще й без «+».
     */
    public static function phone(?string $raw): ?string
    {
        $digits = preg_replace('~\D~', '', (string)$raw) ?? '';
        if (preg_match('~^0\d{9}$~', $digits)) {
            $digits = '38' . $digits;
        }

        return preg_match('~^380\d{9}$~', $digits) ? $digits : null;
    }

    private static function email(?string $raw): ?string
    {
        $email = filter_var(trim((string)$raw), FILTER_VALIDATE_EMAIL);

        return is_string($email) ? $email : null;
    }
}
