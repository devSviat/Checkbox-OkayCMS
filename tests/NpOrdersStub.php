<?php

/**
 * Заглушка фасаду сусіднього модуля Sviat/NovaPoshta.
 *
 * Модуль необов'язковий; у прогоні релізу в рушій кладуть лише Checkbox. Файл
 * підключається лише за відсутності оригіналу, тож на стенді працює справжній клас.
 */

namespace Okay\Modules\Sviat\NovaPoshta\Integration;

final class NpOrders
{
    /** @return list<int> */
    public function receivedSince(string $since): array
    {
        return [];
    }
}
