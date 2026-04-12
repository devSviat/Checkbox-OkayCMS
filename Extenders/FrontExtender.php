<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Extenders;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\Extender\ExtensionInterface;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;

/** Хук фронтенду: додає дані про чек до сторінки замовлення покупця */
class FrontExtender implements ExtensionInterface
{
    private Design $design;
    private EntityFactory $entityFactory;

    public function __construct(Design $design, EntityFactory $entityFactory)
    {
        $this->design = $design;
        $this->entityFactory = $entityFactory;
    }

    /**
     * Після getOrderPurchasesList: якщо є фіскалізований чек для замовлення,
     * передає його в шаблон як $orderReceipt.
     *
     * @param array|false $purchases
     * @return array|false
     */
    public function getOrderPurchasesList($purchases, $orderId)
    {
        if (empty($orderId) || !is_numeric($orderId)) {
            return $purchases;
        }

        $orderIdInt = (int)$orderId;
        if ($orderIdInt <= 0) {
            return $purchases;
        }

        $receiptsEntity = $this->entityFactory->get(FiscalReceiptsEntity::class);
        $receipt = $receiptsEntity->order('id_desc')->findOne(['order_id' => $orderIdInt]);

        if ($receipt && !empty($receipt->receipt_id)) {
            $this->design->assign('orderReceipt', $receipt);
        }

        return $purchases;
    }
}
