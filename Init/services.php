<?php

namespace Okay\Modules\Sviat\Checkbox;

use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\OkayContainer\Reference\ServiceReference as SR;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Extenders\FrontExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use Okay\Modules\Sviat\Checkbox\Compat\AdminIdentity;
use Okay\Modules\Sviat\Checkbox\Compat\SeparateSessionAdminIdentity;
use Okay\Modules\Sviat\Checkbox\Compat\SharedSessionAdminIdentity;

return [
    // Композиційний корінь: рушій визначається один раз, тут. Далі
    // контролери працюють з портом і про різницю не знають. За номером
    // версії рушії не розрізнити — обидва звуть себе 4.5.2.
    AdminIdentity::class => [
        'class' => class_exists('Okay\\Core\\Security\\SessionNames')
            ? SeparateSessionAdminIdentity::class
            : SharedSessionAdminIdentity::class,
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
        ],
    ],
    CheckboxHelper::class => [
        'class' => CheckboxHelper::class,
        'arguments' => [
            new SR(CheckboxApiHelper::class),
            new SR(CheckboxShiftsHelper::class),
            new SR(CheckboxReceiptsHelper::class),
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
