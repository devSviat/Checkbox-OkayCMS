<?php

namespace Okay\Modules\Sviat\Checkbox\Carriers;

/** Замовлення, чию посилку перевізник видав отримувачу: за ними створюється чек «при отриманні». */
interface ReceivedParcels
{
    public function available(): bool;

    /**
     * @param ?string $since sviat__checkbox__installed_at: посилки, отримані раніше, чека не потребують
     * @return list<int>
     */
    public function orderIds(?string $since): array;
}
