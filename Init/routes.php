<?php

namespace Okay\Modules\Sviat\Checkbox;

return [
    'Sviat_Checkbox_createShift' => [
        'slug' => 'backend/sviat/checkbox/ajax/createShift',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptAjaxController',
            'method' => 'createShift',
        ],
    ],
    'Sviat_Checkbox_closeShift' => [
        'slug' => 'backend/sviat/checkbox/ajax/closeShift',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptAjaxController',
            'method' => 'closeShift',
        ],
    ],
    'Sviat_Checkbox_updateShift' => [
        'slug' => 'backend/sviat/checkbox/ajax/updateShift',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptAjaxController',
            'method' => 'updateShift',
        ],
    ],
    'Sviat_Checkbox_createReceipt' => [
        'slug' => 'backend/sviat/checkbox/ajax/createReceipt',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptAjaxController',
            'method' => 'createReceipt',
        ],
    ],
    'Sviat_Checkbox_createPrepaymentReceipt' => [
        'slug' => 'backend/sviat/checkbox/ajax/createPrepaymentReceipt',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptAjaxController',
            'method' => 'createPrepaymentReceipt',
        ],
    ],
    'Sviat_Checkbox_createAfterPaymentReceipt' => [
        'slug' => 'backend/sviat/checkbox/ajax/createAfterPaymentReceipt',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptAjaxController',
            'method' => 'createAfterPaymentReceipt',
        ],
    ],
    'Sviat_Checkbox_refreshChainStatus' => [
        'slug' => 'backend/sviat/checkbox/ajax/refreshChainStatus',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptAjaxController',
            'method' => 'refreshChainStatus',
        ],
    ],
    'Sviat_Checkbox_returnChain' => [
        'slug' => 'backend/sviat/checkbox/ajax/returnChain',
        'to_front' => true,
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptAjaxController',
            'method' => 'returnChain',
        ],
    ],
];
