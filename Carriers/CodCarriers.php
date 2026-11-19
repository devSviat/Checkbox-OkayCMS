<?php

namespace Okay\Modules\Sviat\Checkbox\Carriers;

use Okay\Core\EntityFactory;
use Okay\Core\Settings;
use Okay\Entities\DeliveriesEntity;
use Okay\Entities\ModulesEntity;
use Okay\Entities\PaymentsEntity;

/**
 * Перевізники, чию післяплату позначають на способі оплати ядра. Поля й налаштування — рядками: модулів
 * перевізників може не бути, а Checkbox від них не залежить. Колонку способу оплати сутність віддає лише тоді,
 * коли модуль перевізника увімкнений і зареєстрував її, тож вимкнений модуль рядка не має.
 */
final class CodCarriers
{
    public const string NOVA_POSHTA = 'novaposhta';
    public const string UKRPOSHTA = 'ukrposhta';
    public const string ROZETKA = 'rozetka';

    /** @var array<string, array{module: string, field: string, form: string}> form — перемикач на сторінці способу оплати */
    private const array CARRIERS = [
        self::NOVA_POSHTA => ['module' => 'Sviat/NovaPoshta', 'field' => 'sviat__novaposhta__cod', 'form' => 'sviat_np_cod'],
        self::UKRPOSHTA => ['module' => 'Sviat/Ukrposhta', 'field' => 'sviat__ukrposhta__cod', 'form' => 'sviat_up_cod'],
        self::ROZETKA => ['module' => 'Sviat/RozetkaDelivery', 'field' => 'sviat__rozetka__cod', 'form' => 'sviat_rz_cod'],
    ];

    public function __construct(
        private readonly EntityFactory $entityFactory,
        private readonly ?Settings $settings,
    ) {
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::CARRIERS);
    }

    /**
     * Перевізники, чиї модулі ввімкнені: їхню колонку зареєстровано в сутності способів оплати. Без запиту до бази.
     *
     * @return list<string>
     */
    public static function available(): array
    {
        $fields = PaymentsEntity::getFields();

        return array_values(array_filter(self::keys(), static fn(string $carrier): bool => in_array(self::CARRIERS[$carrier]['field'], $fields, true)));
    }

    /**
     * Чек за післяплату ROZETKA видає магазин лише з `sviat__rozetka__cod_fiscal = shop`; інакше його видає ЕВО
     * чи Rozetka, або не вирішено хто, і чек Checkbox був би другим.
     */
    public function shopIssuesRozetkaReceipts(): bool
    {
        return trim((string)$this->settings?->get('sviat__rozetka__cod_fiscal')) === 'shop';
    }

    /** Ім'я перемикача післяплати перевізника на сторінці способу оплати: за ним скрипт показує рядок. */
    public static function formSwitch(string $carrier): string
    {
        return self::CARRIERS[$carrier]['form'];
    }

    /**
     * Перевізники, чию післяплату позначено на способі оплати. Онлайн-оплата післяплатою не буває.
     *
     * @return list<string>
     */
    public function codOn(object $method): array
    {
        $module = $method->module ?? null;
        if ($module !== null && $module !== '' && $module !== 'null') {
            return [];
        }
        $on = [];
        foreach (self::CARRIERS as $carrier => $spec) {
            if ((int)($method->{$spec['field']} ?? 0) === 0) {
                continue;
            }
            $on[] = $carrier;
        }

        return $on;
    }

    /**
     * Перевізник доставки кожного замовлення за модулем, до якого прив'язано спосіб доставки. Два запити на всю
     * пачку, а не на кожне замовлення.
     *
     * @param list<object> $orders
     * @return array<int, string> за id замовлення; доставки не перевізника з післяплатою пропущено
     */
    public function ofOrders(array $orders): array
    {
        $deliveryOf = [];
        foreach ($orders as $order) {
            if ((int)($order->delivery_id ?? 0) > 0) {
                $deliveryOf[(int)$order->id] = (int)$order->delivery_id;
            }
        }
        $deliveryIds = array_values(array_unique($deliveryOf));
        if ($deliveryIds === []) {
            return [];
        }
        $moduleOf = [];
        foreach ($this->entityFactory->get(DeliveriesEntity::class)->find(['id' => $deliveryIds, 'limit' => count($deliveryIds)]) ?: [] as $delivery) {
            $moduleOf[(int)$delivery->id] = (int)($delivery->module_id ?? 0);
        }
        $moduleIds = array_values(array_unique(array_filter($moduleOf)));
        if ($moduleIds === []) {
            return [];
        }
        $carrierOf = [];
        foreach ($this->entityFactory->get(ModulesEntity::class)->find(['id' => $moduleIds, 'limit' => count($moduleIds)]) ?: [] as $module) {
            foreach (self::CARRIERS as $carrier => $spec) {
                if ($spec['module'] === $module->vendor . '/' . $module->module_name) {
                    $carrierOf[(int)$module->id] = $carrier;
                }
            }
        }
        $result = [];
        foreach ($deliveryOf as $orderId => $deliveryId) {
            $carrier = $carrierOf[$moduleOf[$deliveryId] ?? 0] ?? null;
            if ($carrier !== null) {
                $result[$orderId] = $carrier;
            }
        }

        return $result;
    }
}
