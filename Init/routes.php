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
    'Sviat_Checkbox_cronShiftsCheck' => [
        'slug' => 'cron/sviat/checkbox/checkShifts',
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptCronController',
            'method' => 'checkShifts',
        ],
    ],
    'Sviat_Checkbox_cronReceiptsCheckEmpty' => [
        'slug' => 'cron/sviat/checkbox/checkEmptyReceipts',
        'params' => [
            'controller' => __NAMESPACE__ . '\Controllers\FiscalReceiptCronController',
            'method' => 'checkEmptyReceipts',
        ],
    ],
];
