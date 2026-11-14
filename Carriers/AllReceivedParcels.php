<?php

namespace Okay\Modules\Sviat\Checkbox\Carriers;

/** Видані посилки всіх перевізників; замовлення, що є в обох, береться один раз. */
final class AllReceivedParcels implements ReceivedParcels
{
    public function __construct(
        private readonly NovaPoshtaReceived $novaPoshta,
        private readonly UkrposhtaReceived $ukrposhta,
    ) {
    }

    public function available(): bool
    {
        return $this->novaPoshta->available() || $this->ukrposhta->available();
    }

    public function orderIds(?string $since): array
    {
        return array_values(array_unique([...$this->novaPoshta->orderIds($since), ...$this->ukrposhta->orderIds($since)]));
    }
}
