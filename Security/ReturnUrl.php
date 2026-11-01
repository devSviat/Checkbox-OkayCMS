<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Security;

/**
 * Параметр ?return= для кнопки «Назад» в адмінці.
 *
 * На стоку ядро його не чистить, а |escape не зупиняє javascript:, тому
 * приймається лише шлях у межах сайту.
 */
final class ReturnUrl
{
    public static function safe($value): ?string
    {
        if (!is_string($value) || $value === '' || preg_match('/[\x00-\x1f\x7f]/', $value)) {
            return null;
        }

        return preg_match('#^/(?![/\\\\])#', $value) === 1 ? $value : null;
    }
}
