<?php

namespace Okay\Modules\Sviat\Checkbox\Carriers;

use Closure;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Modules;
use Okay\Core\ServiceLocator;
use Okay\Modules\Sviat\NovaPoshta\Integration\NpOrders;
use Okay\Modules\Sviat\NovaPoshtaTracking\Entities\NovaPoshtaTrackingEntity;

/**
 * Замовлення, чию посилку Нова Пошта видала отримувачу. Питає модуль Sviat/NovaPoshta і старий
 * NovaPoshtaTracking — той, що ввімкнений; поки встановлені обидва, замовлення з обох.
 */
final class NovaPoshtaReceived implements ReceivedParcels
{
    private const string RECEIVED = '9';
    private const string BEGINNING = '1970-01-01 00:00:00';

    private readonly Closure $npOrders;

    /** @param ?Closure(): object $npOrders фасад NpOrders; береться лише коли модуль увімкнений */
    public function __construct(
        private readonly Modules $modules,
        private readonly EntityFactory $entityFactory,
        ?Closure $npOrders = null,
    ) {
        $this->npOrders = $npOrders ?? static fn(): object => ServiceLocator::getInstance()->getService(NpOrders::class);
    }

    public function available(): bool
    {
        return $this->newActive() || $this->oldActive();
    }

    /**
     * @param ?string $since sviat__checkbox__installed_at: посилки, отримані раніше, чека не потребують
     * @return list<int>
     */
    public function orderIds(?string $since): array
    {
        $ids = $this->newActive() ? ($this->npOrders)()->receivedSince($since ?? self::BEGINNING) : [];
        if ($this->oldActive()) {
            $ids = [...$ids, ...$this->oldIds($since)];
        }

        return array_values(array_unique(array_map(intval(...), $ids)));
    }

    /** @return list<int> */
    private function oldIds(?string $since): array
    {
        $from = $since !== null ? strtotime($since) : false;
        $ids = [];
        foreach ($this->entityFactory->get(NovaPoshtaTrackingEntity::class)->find(['status_code' => self::RECEIVED]) as $tracking) {
            if ($from !== false && (empty($tracking->updated_at) || strtotime($tracking->updated_at) < $from)) {
                continue;
            }
            if (!empty($tracking->order_id)) {
                $ids[] = (int)$tracking->order_id;
            }
        }

        return $ids;
    }

    private function newActive(): bool
    {
        return class_exists(NpOrders::class) && $this->modules->isActiveModule('Sviat', 'NovaPoshta');
    }

    private function oldActive(): bool
    {
        return class_exists(NovaPoshtaTrackingEntity::class) && $this->modules->isActiveModule('Sviat', 'NovaPoshtaTracking');
    }
}
