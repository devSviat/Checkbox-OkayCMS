<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Entities;

use Okay\Core\Entity\Entity;
use Okay\Modules\Sviat\Checkbox\Init\Init;

/**
 * Податкові групи Checkbox (коди ПДВ).
 * Зв'язок товарів з групами зберігається в PRODUCT_TAX_GROUPS_TABLE.
 */
class TaxGroupsEntity extends Entity
{
    protected static $fields = ['id', 'code', 'name'];

    protected static $defaultOrderFields = ['id DESC'];

    protected static $table = '__sviat__checkbox__tax_groups';

    /** Видаляє групу разом із прив'язками до товарів */
    public function delete($ids)
    {
        if (empty($ids)) {
            return parent::delete($ids);
        }
        $this->deleteTaxProducts(is_array($ids) ? $ids : [(int)$ids]);
        return parent::delete($ids);
    }

    /** Повертає масив tax_id прив'язаних до товару */
    public function getProductTaxGroups(int $productId): array
    {
        $select = $this->queryFactory->newSelect();
        $select->cols(['tax_id'])
            ->from(Init::PRODUCT_TAX_GROUPS_TABLE)
            ->where('product_id=:productId')
            ->bindValue('productId', $productId);

        $this->db->query($select);
        $results = $this->db->results('tax_id');
        return is_array($results) ? $results : [];
    }

    /** Повертає масив числових кодів ПДВ для товару (використовується при формуванні чека) */
    public function getProductTaxGroupCodes(int $productId): array
    {
        $select = $this->queryFactory->newSelect();
        $select->cols(['code'])
            ->from($this->getTable())
            ->join('left', Init::PRODUCT_TAX_GROUPS_TABLE, 'id=tax_id')
            ->where('product_id=:productId')
            ->bindValue('productId', $productId);

        $this->db->query($select);
        $results = $this->db->results('code');
        return is_array($results) ? $results : [];
    }

    /** Додає або оновлює зв'язок товару з податковою групою (REPLACE INTO) */
    public function addProductTaxGroup(int $productId, int $taxId): void
    {
        $query = $this->queryFactory->newSqlQuery();
        $query->setStatement(
            "REPLACE INTO " . Init::PRODUCT_TAX_GROUPS_TABLE . " SET product_id=:productId, tax_id=:taxId"
        )->bindValues(['productId' => $productId, 'taxId' => $taxId]);
        $this->db->query($query);
    }

    /** Видаляє всі прив'язки до груп ПДВ для одного або кількох товарів */
    public function deleteProductTaxGroups($productIds): void
    {
        $delete = $this->queryFactory->newDelete();
        $delete->from(Init::PRODUCT_TAX_GROUPS_TABLE)
            ->where('product_id IN(:productIds)')
            ->bindValue('productIds', is_array($productIds) ? $productIds : [(int)$productIds]);
        $this->db->query($delete);
    }

    /** Видаляє всі прив'язки товарів для вказаних груп ПДВ */
    public function deleteTaxProducts(array $taxIds): void
    {
        if (empty($taxIds)) {
            return;
        }
        $delete = $this->queryFactory->newDelete();
        $delete->from(Init::PRODUCT_TAX_GROUPS_TABLE)
            ->where('tax_id IN(:taxIds)')
            ->bindValue('taxIds', $taxIds);
        $this->db->query($delete);
    }
}
