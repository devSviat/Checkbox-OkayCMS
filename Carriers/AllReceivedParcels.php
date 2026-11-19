<?php

namespace Okay\Modules\Sviat\Checkbox\Carriers;

/** Видані посилки всіх перевізників; замовлення, що є в кількох, береться один раз. */
final class AllReceivedParcels implements ReceivedParcels
{
    /** @var list<ReceivedParcels> */
    private readonly array $sources;

    public function __construct(ReceivedParcels ...$sources)
    {
        $this->sources = array_values($sources);
    }

    public function available(): bool
    {
        foreach ($this->sources as $source) {
            if ($source->available()) {
                return true;
            }
        }

        return false;
    }

    public function orderIds(?string $since): array
    {
        $ids = [];
        foreach ($this->sources as $source) {
            $ids = [...$ids, ...$source->orderIds($since)];
        }

        return array_values(array_unique($ids));
    }
}
