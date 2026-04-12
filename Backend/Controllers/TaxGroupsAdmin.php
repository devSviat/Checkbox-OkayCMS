<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Backend\Controllers;

use Okay\Admin\Controllers\IndexAdmin;
use Okay\Core\EntityFactory;
use Okay\Modules\Sviat\Checkbox\Entities\TaxGroupsEntity;

class TaxGroupsAdmin extends IndexAdmin
{
    public function fetch(EntityFactory $entityFactory)
    {
        $taxGroupsEntity = $entityFactory->get(TaxGroupsEntity::class);

        if ($this->request->method('post')) {
            $ids = $this->request->post('check');
            if (is_array($ids) && $this->request->post('action') === 'delete') {
                $taxGroupsEntity->delete($ids);
            }
        }

        $filter = [];
        $filter['page'] = max(1, $this->request->get('page', 'integer'));
        $filter['limit'] = 20;

        $taxesCount = $taxGroupsEntity->count($filter);
        if ($this->request->get('page') == 'all') {
            $filter['limit'] = $taxesCount;
        }

        $this->design->assign('taxes_count', $taxesCount);
        $this->design->assign('pages_count', ceil($taxesCount / $filter['limit']));
        $this->design->assign('current_page', $filter['page']);
        $this->design->assign('taxes', $taxGroupsEntity->find($filter));

        $this->response->setContent($this->design->fetch('checkbox_taxes.tpl'));
    }
}
