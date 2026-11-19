<?php

namespace Okay\Modules\Sviat\Checkbox\Carriers;

use Closure;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Core\Settings;
use Okay\Modules\Sviat\RozetkaDelivery\Integration\RozetkaOrders;

/**
 * Замовлення, чию післяплату ROZETKA видано отримувачу й оплачено. Лише коли в модулі доставки обрано, що чек
 * видає магазин: інакше його може видати ЕВО чи Rozetka, і другий чек на ті самі гроші був би хибним.
 * Без модуля Sviat/RozetkaDelivery або коли він вимкнений, їх немає.
 */
final class RozetkaReceived implements ReceivedParcels
{
    private const string BEGINNING = '1970-01-01 00:00:00';
    /** Налаштування модуля доставки; рядком, бо класу його конфігурації може не бути. */
    private const string FISCAL_SETTING = 'sviat__rozetka__cod_fiscal';
    private const string FISCAL_SHOP = 'shop';
    /**
     * Коли модуль доставки перейшов на «магазин». Посилки, видані раніше, міг фіскалізувати хтось інший; без дати
     * (значення записане повз сторінку налаштувань) автоматика нічого не бере.
     */
    private const string FISCAL_SHOP_SINCE = 'sviat__rozetka__cod_fiscal_shop_since';

    private readonly Closure $rozetkaOrders;

    /** @param ?Closure(): object $rozetkaOrders фасад RozetkaOrders; береться лише коли чек видає магазин */
    public function __construct(
        private readonly Modules $modules,
        private readonly Settings $settings,
        ?Closure $rozetkaOrders = null,
    ) {
        $this->rozetkaOrders = $rozetkaOrders ?? static fn(): object => ServiceLocator::getInstance()->getService(RozetkaOrders::class);
    }

    public function available(): bool
    {
        $fiscal = $this->settings->get(self::FISCAL_SETTING);

        return is_string($fiscal) && trim($fiscal) === self::FISCAL_SHOP
            && class_exists(RozetkaOrders::class)
            && $this->modules->isActiveModule('Sviat', 'RozetkaDelivery');
    }

    public function orderIds(?string $since): array
    {
        if (!$this->available()) {
            return [];
        }
        $switched = $this->settings->get(self::FISCAL_SHOP_SINCE);
        if (!is_string($switched) || strtotime($switched) === false) {
            return [];
        }
        $from = date('Y-m-d H:i:s', max(strtotime($switched), strtotime($since ?? self::BEGINNING) ?: 0));
        return array_values(array_unique(array_map(intval(...), ($this->rozetkaOrders)()->receivedCodSince($from))));
    }
}
