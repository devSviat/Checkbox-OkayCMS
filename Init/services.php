<?php

namespace Okay\Modules\Sviat\Checkbox;

use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Extenders\FrontExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPrepaymentHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use Okay\Modules\Sviat\Checkbox\Security\AdminIdentity;

return [
    AdminIdentity::class => [
        'class' => AdminIdentity::class,
        'arguments' => [],
    ],
    CheckboxApiHelper::class => [
        'class' => CheckboxApiHelper::class,
        'arguments' => [],
    ],
    CheckboxShiftsHelper::class => [
        'class' => CheckboxShiftsHelper::class,
        'arguments' => [],
    ],
    CheckboxReceiptsHelper::class => [
        'class' => CheckboxReceiptsHelper::class,
        'arguments' => [
            new SR(CheckboxShiftsHelper::class),
            new SR(CheckboxPrepaymentHelper::class),
        ],
    ],
    CheckboxPrepaymentHelper::class => [
        'class' => CheckboxPrepaymentHelper::class,
        'arguments' => [],
    ],
    CheckboxHelper::class => [
        'class' => CheckboxHelper::class,
        'arguments' => [
            new SR(CheckboxApiHelper::class),
            new SR(CheckboxShiftsHelper::class),
            new SR(CheckboxReceiptsHelper::class),
            new SR(CheckboxPrepaymentHelper::class),
        ],
    ],
    BackendExtender::class => [
        'class' => BackendExtender::class,
        'arguments' => [
            new SR(Design::class),
            new SR(Request::class),
            new SR(Settings::class),
            new SR(EntityFactory::class),
            new SR(CheckboxHelper::class),
            new SR(BackendTranslations::class),
        ],
    ],
    FrontExtender::class => [
        'class' => FrontExtender::class,
        'arguments' => [
            new SR(Design::class),
            new SR(EntityFactory::class),
        ],
    ],
];
