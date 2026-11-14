<?php

namespace Okay\Modules\Sviat\Checkbox\Carriers;

use Closure;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\Ukrposhta\Integration\UpOrders;

/** Замовлення, чию посилку Укрпошта видала отримувачу; без модуля Sviat/Ukrposhta або коли він вимкнений, їх немає. */
final class UkrposhtaReceived implements ReceivedParcels
{
    private const string BEGINNING = '1970-01-01 00:00:00';

    private readonly Closure $upOrders;

    /** @param ?Closure(): object $upOrders фасад UpOrders; береться лише коли модуль увімкнений */
    public function __construct(private readonly Modules $modules, ?Closure $upOrders = null)
    {
        $this->upOrders = $upOrders ?? static fn(): object => ServiceLocator::getInstance()->getService(UpOrders::class);
    }

    public function available(): bool
    {
        return class_exists(UpOrders::class) && $this->modules->isActiveModule('Sviat', 'Ukrposhta');
    }

    public function orderIds(?string $since): array
    {
        if (!$this->available()) {
            return [];
        }

        return array_values(array_unique(array_map(intval(...), ($this->upOrders)()->receivedSince($since ?? self::BEGINNING))));
    }
}
