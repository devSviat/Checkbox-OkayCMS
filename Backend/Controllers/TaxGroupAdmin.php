<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;

class TaxGroupAdmin extends IndexAdmin
{
    public function fetch(EntityFactory $entityFactory)
    {
        $taxGroupsEntity = $entityFactory->get(TaxGroupsEntity::class);
        $tax = new \stdClass();

        if ($this->request->method('post')) {
            $taxId = $this->request->post('id', 'integer');
            $tax->id = $taxId > 0 ? $taxId : null;
            $tax->code = $this->request->post('code', 'integer');
            $tax->name = $this->request->post('name');

            if (empty($tax->code)) {
                $this->design->assign('message_error', 'empty_code');
            } elseif (empty($tax->name)) {
                $this->design->assign('message_error', 'empty_name');
            } else {
                $existTax = $taxGroupsEntity->findOne(['code' => $tax->code]);
                // Дублікат коду: помилка при створенні або якщо інший запис вже має цей код
                if ($existTax && (empty($tax->id) || $existTax->id != $tax->id)) {
                    $this->design->assign('message_error', 'exists_code');
                } elseif (empty($tax->id)) {
                    $tax->id = $taxGroupsEntity->add($tax);
                    $tax = $taxGroupsEntity->get($tax->id);
                    $this->design->assign('message_success', 'added');
                } else {
                    $taxGroupsEntity->update($tax->id, $tax);
                    $tax = $taxGroupsEntity->get($tax->id);
                    $this->design->assign('message_success', 'updated');
                }
            }
        } else {
            $taxId = $this->request->get('id', 'integer');
            if ($taxId > 0) {
                $tax = $taxGroupsEntity->findOne(['id' => $taxId]);
            }
        }

        $this->design->assign('tax', $tax);
        $this->response->setContent($this->design->fetch('checkbox_tax.tpl'));
    }
}
