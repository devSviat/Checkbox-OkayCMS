<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;

/**
 * Питання до набору чеків замовлення.
 *
 * Винесено з трьох викликачів, які ставили це питання по-різному: два хуки
 * адмінки й розвилка автоматики. Відповідь керує тим, чи виставляти чек, тож
 * розбіжність між ними коштувала б другого фіскального чека.
 */
final class CheckboxReceiptSet
{
    /**
     * Чи є серед чеків замовлення виставлений чек продажу.
     *
     * Тип читається з властивості, а не з фільтра запиту, і відсутній тип
     * означає продаж. Це не косметика: поки міграція на проді ще не виконалась,
     * колонки receipt_type немає, вибірка з фільтром по ній падає, а помилки SQL
     * тут ковтаються мовчки — порожній результат прочитався б як «чека немає» і
     * дав би дубль на живі гроші.
     *
     * @param iterable $receipts рядки таблиці чеків цього замовлення
     */
    public static function hasSaleReceipt($receipts): bool
    {
        foreach ($receipts as $receipt) {
            $type = $receipt->receipt_type ?? FiscalReceiptsEntity::TYPE_SALE;
            if ($type === FiscalReceiptsEntity::TYPE_SALE && !empty($receipt->receipt_id)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Чи лишився чек продажу, який ще нічим не повернуто.
     *
     * Питання інше, ніж у hasSaleReceipt(), і відповідає за інше рішення.
     * Автоматика питає «чи фіскалізували колись» — повторно виставляти чек їй
     * не можна навіть після повернення. Кнопки на картці питають «чи є що
     * повертати»: без цього кнопка повернення не зникає ніколи, і другий чек
     * повернення на той самий продаж робиться одним кліком.
     *
     * Повернення прив'язане до продажу через related_receipt_id.
     *
     * @param iterable $receipts рядки таблиці чеків цього замовлення
     */
    public static function hasUncoveredSaleReceipt($receipts): bool
    {
        $sales = [];
        $returned = [];

        foreach ($receipts as $receipt) {
            if (empty($receipt->receipt_id)) {
                continue;
            }
            $type = $receipt->receipt_type ?? FiscalReceiptsEntity::TYPE_SALE;
            if ($type === FiscalReceiptsEntity::TYPE_SALE) {
                $sales[(string)$receipt->receipt_id] = true;
            } elseif ($type === FiscalReceiptsEntity::TYPE_RETURN && !empty($receipt->related_receipt_id)) {
                $returned[(string)$receipt->related_receipt_id] = true;
            }
        }

        foreach ($sales as $receiptId => $_) {
            if (!isset($returned[$receiptId])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Скільки заготовок чекає на відкриту зміну.
     *
     * Рахуються лише продаж і повернення. Рядок-намір ланцюжка теж має порожній
     * receipt_id, але чекає на відповідь Checkbox, а не на зміну; попередження
     * про незавершені чеки ховає на картці всі дії, тож зарахувати його сюди
     * означало б замкнути менеджера після першого ж таймауту.
     *
     * @param iterable $receipts рядки таблиці чеків цього замовлення
     */
    public static function countUnfinished($receipts): int
    {
        $count = 0;
        foreach ($receipts as $receipt) {
            if (!empty($receipt->receipt_id)) {
                continue;
            }
            $type = $receipt->receipt_type ?? FiscalReceiptsEntity::TYPE_SALE;
            if ($type === FiscalReceiptsEntity::TYPE_SALE || $type === FiscalReceiptsEntity::TYPE_RETURN) {
                $count++;
            }
        }

        return $count;
    }
}
