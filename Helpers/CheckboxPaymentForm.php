<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Джерело коштів → блок `payments` фіскального чека.
 *
 * `type` лягає в рядок 18 чека («ГОТІВКА»/«БЕЗГОТІВКОВА»), `label` — у рядок 19
 * («засіб платежу»). І те, і те бере CheckboxPaymentCatalogue; що з каталогу
 * пропонується менеджеру — вирішує CheckboxPaymentSources.
 *
 * Розрізняти джерела обов'язково не лише через мітку: переказ з рахунку клієнта
 * через банк узагалі не потребує фіскалізації, а той самий платіж карткою —
 * потребує.
 */
final class CheckboxPaymentForm
{
    /**
     * Джерела, які називає сам код: автоматика виводить їх зі способу оплати
     * замовлення, тож вони мусять лишатися чинними навіть тоді, коли магазин
     * прибрав їх зі списку на картці.
     */
    const SOURCE_BANK_ACCOUNT = 'bank_account';
    const SOURCE_INTEGRATOR   = 'integrator';
    const SOURCE_CASH         = 'cash';

    public static function isKnown(string $source): bool
    {
        return CheckboxPaymentCatalogue::isKnown($source);
    }

    /**
     * @param string|null $labelOverride мітка зі способу оплати замовлення або з
     *                                   налаштувань джерел. Автоматичні чеки
     *                                   несуть саме її, щоб одне замовлення не
     *                                   дало податковій два різні засоби
     *                                   платежу. Тип лишається за джерелом: він
     *                                   визначає CASH/CASHLESS.
     * @return array{type: string, label: string, value: int}
     * @throws \InvalidArgumentException на невідомому джерелі
     */
    public static function payment(string $source, int $valueKopiyky, ?string $labelOverride = null): array
    {
        // Останні ворота перед чеком. Ключ із назвою там, де каталог її не
        // передбачив («cash:Готівочка»), мітку не змінює — назва просто нікуди
        // не підставляється, — але це вже не той засіб платежу, який назвали.
        if (!CheckboxPaymentCatalogue::isKnown($source)) {
            throw new \InvalidArgumentException('Невідоме джерело коштів: ' . $source);
        }

        $label = CheckboxPaymentCatalogue::defaultLabel($source);
        if ($labelOverride !== null && CheckboxPaymentCatalogue::labelIsValid($labelOverride)) {
            $label = trim($labelOverride);
        }

        // Джерело з шаблоном замість назви й без заміни: далі — рядок 19
        // фіскального чека з кутовими дужками. Краще впасти тут.
        if (!CheckboxPaymentCatalogue::labelIsValid($label)) {
            throw new \InvalidArgumentException('Джерело коштів без назви: ' . $source);
        }

        return [
            'type'  => CheckboxPaymentCatalogue::type($source),
            'label' => $label,
            'value' => $valueKopiyky,
        ];
    }
}
