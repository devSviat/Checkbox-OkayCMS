<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Яке джерело коштів форма показує вибраним, коли відкривається.
 *
 * Це лише підказка у формі: менеджер бачить її до натискання й може змінити,
 * а сервер перевіряє надіслане джерело сам. Тому кандидат, якого вже немає в
 * списку (магазин вимкнув джерело), пропускається, а не підставляється: select
 * без такого пункту віддав би порожнє значення.
 */
final class CheckboxSourceChoice
{
    /**
     * Перший кандидат, який є серед джерел списку, або порожній рядок.
     *
     * @param array<int, array{key: string, label: string, type: string}> $sources
     */
    public static function pick(array $sources, mixed ...$candidates): string
    {
        $keys = array_column($sources, 'key');
        foreach ($candidates as $candidate) {
            if (is_string($candidate) && $candidate !== '' && in_array($candidate, $keys, true)) {
                return $candidate;
            }
        }

        return '';
    }

    /**
     * Джерело для ручного закриття ланцюжка: те, яким прийшов аванс, далі
     * типове з налаштувань. Без обох — виведене зі способу оплати замовлення,
     * а якщо магазин прибрав і його зі списку, то перше з наявних: select без
     * вибраного пункту віддав би порожнє значення, і кнопка відмовила б без
     * видимої причини.
     *
     * @param array<int, array{key: string, label: string, type: string}> $sources
     */
    public static function afterPayment(array $sources, mixed $advanceSource, mixed $defaultSource, string $orderPaymentSource): string
    {
        $chosen = self::pick($sources, $advanceSource, $defaultSource, $orderPaymentSource);

        return $chosen !== '' ? $chosen : (string)($sources[0]['key'] ?? '');
    }
}
