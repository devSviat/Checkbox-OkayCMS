<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Побудова позицій фіскального чека та суми оплати.
 *
 * Винесено з CheckboxReceiptsHelper::createReceipt() без зміни поведінки: сам
 * метод неможливо перевірити на місці — його конструктор через parent іде в
 * ServiceLocator, а тіло робить session_write_close() і чистить $_SESSION.
 *
 * УВАГА при подальших правках: цикл навмисно мутує $purchase->price на місці.
 * $purchases — масив ОБ'ЄКТІВ, тож передача за значенням мутацію зберігає, і
 * код нижче за течією (запис чека в базу, лист покупцеві) читає вже
 * перераховану ціну. Переписування на value-семантику тихо змінить поведінку.
 */
final class CheckboxReceiptPayloadBuilder
{
    /**
     * @param object[] $purchases      Позиції замовлення (мутуються: ->price).
     * @param float    $undiscountedTotal Сума до знижки.
     * @param float    $totalPrice     Сума до сплати.
     * @param bool     $isReturn       Чек повернення.
     *
     * @return array{goods: array<int, array>, paymentValue: int}
     */
    public static function build(
        array $purchases,
        float $undiscountedTotal,
        float $totalPrice,
        bool $isReturn = false
    ): array {
        // Знижка розподіляється пропорційно на ціну кожного товару.
        $discountAmount = 0.0;
        $discountPercent = null;
        if ($undiscountedTotal != $totalPrice && $undiscountedTotal > 0) {
            $discountAmount = $undiscountedTotal - $totalPrice;
            $discountPercent = (1 - ($totalPrice / $undiscountedTotal)) * 100;
        }

        // Checkbox звіряє суму оплати із сумою позицій, тож накопичувати її
        // можна лише з уже округлених рядків, а не з вихідних цін.
        $goods = [];
        $paymentValue = 0;

        foreach ($purchases as $purchase) {
            $purchasePrice = is_numeric($purchase->price) ? (float)$purchase->price : 0.0;
            $purchaseAmount = is_numeric($purchase->amount) ? (int)$purchase->amount : 0;
            $variantId = is_numeric($purchase->variant_id) ? (int)$purchase->variant_id : 0;

            if ($discountAmount > 0 && $discountPercent !== null) {
                $purchasePrice = $purchasePrice - ($purchasePrice * ($discountPercent / 100));
                $purchase->price = $purchasePrice;
            }

            $priceKopiyky = self::toKopiyky($purchasePrice);
            $paymentValue += $priceKopiyky * $purchaseAmount;

            $productName = $purchase->fullProductName
                ?? ($purchase->product_name . (!empty($purchase->variant_name) ? (' - ' . $purchase->variant_name) : ''));

            $goodItem = [
                'good' => [
                    'code' => (string)$variantId,
                    'name' => $productName,
                    'price' => $priceKopiyky,
                    'tax' => is_array($purchase->taxes ?? null) ? $purchase->taxes : []
                ],
                'quantity' => $purchaseAmount * 1000
            ];
            if ($isReturn) {
                $goodItem['is_return'] = true;
            }
            $goods[] = $goodItem;
        }

        return ['goods' => $goods, 'paymentValue' => $paymentValue];
    }

    /**
     * Гривні → копійки.
     *
     * PHP 8.4 округлює саме те число з плаваючою комою, яке отримала:
     * 1.005 * 100 у double — це 100.4999…, тож без проміжного округлення до
     * 6 знаків копійка в чеку загубилась би.
     */
    public static function toKopiyky(float $hryvnia): int
    {
        return (int)round(round($hryvnia * 100, 6));
    }
}
