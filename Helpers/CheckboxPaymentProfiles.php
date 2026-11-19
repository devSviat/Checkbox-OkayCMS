<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Core\EntityFactory;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\Checkbox\Carriers\CodCarriers;
use Okay\Modules\Sviat\Checkbox\Init\Init;

/**
 * Які форма, засіб оплати й «не відправляти» діють для чека замовлення. Один спосіб оплати буває післяплатою
 * кількох перевізників, а засіб оплати в чеку в кожного свій. Рядок перевізника діє, лише коли його післяплату
 * позначено на способі оплати і доставка замовлення — його; інакше діє рядок самого способу оплати.
 */
final class CheckboxPaymentProfiles
{
    private const array TYPES = ['CASH', 'CASHLESS'];
    private const int LABEL_MAX = 255;

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly CodCarriers $carriers,
    ) {
    }

    /** @param ?object $method спосіб оплати замовлення, якщо вже прочитаний */
    public function forOrder(object $order, ?object $method = null): ?CheckboxPaymentProfile
    {
        if ($method === null) {
            $methodId = (int)($order->payment_method_id ?? 0);
            $method = $methodId > 0 ? $this->entityFactory->get(PaymentsEntity::class)->findOne(['id' => $methodId]) : null;
            if (!$method) {
                return null;
            }
        }

        return $this->forOrders([$order], [(int)($order->payment_method_id ?? 0) => $method])[(int)$order->id] ?? null;
    }

    /**
     * Рядки для пачки замовлень. Доставку питаємо лише для замовлень, де хоч одну післяплату позначено, і одним
     * запитом на пачку.
     *
     * @param iterable<object> $orders
     * @param array<int, object> $methods способи оплати за id
     * @return array<int, CheckboxPaymentProfile> за id замовлення; без способу оплати — немає
     */
    public function forOrders(iterable $orders, array $methods): array
    {
        $profiles = [];
        $candidates = [];
        $codOn = [];
        foreach ($orders as $order) {
            $method = $methods[(int)($order->payment_method_id ?? 0)] ?? null;
            if (!$method) {
                continue;
            }
            $profiles[(int)$order->id] = CheckboxPaymentProfile::fromMethod($method);
            $on = $this->carriers->codOn($method);
            if ($on !== []) {
                $candidates[] = $order;
                $codOn[(int)$order->id] = [$on, $method];
            }
        }
        if ($candidates === []) {
            return $profiles;
        }
        foreach ($this->carriers->ofOrders($candidates) as $orderId => $carrier) {
            [$on, $method] = $codOn[$orderId];
            if (in_array($carrier, $on, true)) {
                $profile = self::override($carrier, $method, $profiles[$orderId]);
                if ($carrier === CodCarriers::ROZETKA && !$this->carriers->shopIssuesRozetkaReceipts()) {
                    $profile = new CheckboxPaymentProfile($profile->type, $profile->label, true);
                }
                $profiles[$orderId] = $profile;
            }
        }

        return $profiles;
    }

    /**
     * Рядок перевізника, поки його не змінювали. НП — рядок способу оплати, тож її чеки після оновлення не
     * змінюються. Укрпошта — безготівкова «Післяплата».
     * ROZETKA — безготівковий «Платіж ROZETKAPAY» з довідки продавця Rozetka; чи слати його, вирішує
     * `cod_fiscal` модуля ROZETKA, а не «не відправляти» способу оплати.
     */
    public static function defaults(string $carrier, CheckboxPaymentProfile $base): CheckboxPaymentProfile
    {
        return match ($carrier) {
            CodCarriers::ROZETKA => new CheckboxPaymentProfile('CASHLESS', 'Платіж ROZETKAPAY', false),
            CodCarriers::UKRPOSHTA => new CheckboxPaymentProfile('CASHLESS', 'Післяплата', $base->skip),
            default => $base,
        };
    }

    public static function override(string $carrier, object $method, CheckboxPaymentProfile $base): CheckboxPaymentProfile
    {
        $default = self::defaults($carrier, $base);
        $saved = self::decode($method->{Init::CARRIER_PAYMENTS_FIELD} ?? null)[$carrier] ?? null;
        if ($saved === null) {
            return $default;
        }
        $type = in_array($saved['type'] ?? null, self::TYPES, true) ? $saved['type'] : $default->type;
        $label = is_string($saved['label'] ?? null) ? trim($saved['label']) : (string)$default->label;
        $skip = is_bool($saved['skip'] ?? null) ? $saved['skip'] : $default->skip;

        return new CheckboxPaymentProfile($type, $label !== '' ? mb_substr($label, 0, self::LABEL_MAX) : null, $skip);
    }

    /** @return array<string, array<string, mixed>> лише відомі перевізники з масивом полів */
    public static function decode(mixed $json): array
    {
        $rows = is_string($json) ? json_decode($json, true) : null;
        if (!is_array($rows)) {
            return [];
        }

        return array_filter(
            array_intersect_key($rows, array_flip(CodCarriers::keys())),
            is_array(...),
        );
    }

    /**
     * Надіслані рядки поверх збережених. Рядок, що дорівнює своєму типовому для нового рядка способу оплати, не
     * записується й зі збережених зникає: інакше будь-яке збереження сторінки «заморозило» б рядок НП на старих
     * значеннях способу оплати.
     *
     * @param array<string, CheckboxPaymentProfile> $rows
     */
    public static function encode(mixed $stored, CheckboxPaymentProfile $base, array $rows): ?string
    {
        $all = self::decode($stored);
        foreach ($rows as $carrier => $profile) {
            if (!in_array($carrier, CodCarriers::keys(), true)) {
                continue;
            }
            if ($profile->toArray() === self::defaults($carrier, $base)->toArray()) {
                unset($all[$carrier]);
            } else {
                $all[$carrier] = $profile->toArray();
            }
        }

        return $all === [] ? null : json_encode($all, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Рядки перевізників для сторінки способу оплати: лише ввімкнені модулі. Рядок, чию післяплату не позначено,
     * сторінка ховає, доки менеджер не ввімкне перемикач.
     *
     * @return list<array{carrier: string, switch: string, on: bool, type: string, label: string, skip: bool}>
     */
    public function rows(object $method): array
    {
        $base = CheckboxPaymentProfile::fromMethod($method);
        $on = $this->carriers->codOn($method);
        $rows = [];
        foreach (CodCarriers::available() as $carrier) {
            $rows[] = ['carrier' => $carrier, 'switch' => CodCarriers::formSwitch($carrier), 'on' => in_array($carrier, $on, true)]
                + self::override($carrier, $method, $base)->toArray();
        }

        return $rows;
    }

    /**
     * Рядки з форми. Сторінка надсилає лише показані рядки; рядок з невідомою формою оплати не записується.
     *
     * @return array<string, CheckboxPaymentProfile>
     */
    public static function fromPost(mixed $posted): array
    {
        if (!is_array($posted)) {
            return [];
        }
        $rows = [];
        foreach (CodCarriers::available() as $carrier) {
            $row = $posted[$carrier] ?? null;
            if (!is_array($row) || !in_array($row['type'] ?? null, self::TYPES, true)) {
                continue;
            }
            $label = is_string($row['label'] ?? null) ? trim($row['label']) : '';
            $rows[$carrier] = new CheckboxPaymentProfile($row['type'], $label !== '' ? mb_substr($label, 0, self::LABEL_MAX) : null, !empty($row['skip']));
        }

        return $rows;
    }
}
