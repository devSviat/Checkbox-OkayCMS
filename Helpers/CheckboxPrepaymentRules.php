<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/** Правила сум ланцюжка, які можна перевірити до мережевого виклику. */
final class CheckboxPrepaymentRules
{
    /**
     * Аванс мусить бути СТРОГО меншим за вартість товарів — рівну суму Checkbox
     * відхиляє, бо це вже не передплата, а звичайний продаж.
     */
    public static function advanceIsValid(int $advanceKopiyky, int $goodsTotalKopiyky): bool
    {
        return $advanceKopiyky > 0 && $advanceKopiyky < $goodsTotalKopiyky;
    }
}
