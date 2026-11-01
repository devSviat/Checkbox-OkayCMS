<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Що означає відповідь Checkbox для чека.
 *
 * Головна межа: REJECTED — чека точно немає, UNKNOWN — він міг створитись, а
 * відповідь не дійшла. Повторити можна лише з тим самим id: новий дав би другий
 * фіскальний документ.
 */
final class CheckboxApiOutcome
{
    const string SUCCESS         = 'success';
    const string SHIFT_CLOSED    = 'shift_closed';
    const string ALREADY_EXISTS  = 'already_exists';
    const string DOCUMENT_FAILED = 'document_failed';
    const string REJECTED        = 'rejected';
    const string UNKNOWN         = 'unknown';

    private const array RETRYABLE_HTTP = [408, 425, 429];

    /**
     * @param array $errors   CheckboxApiHelper::$errors після запиту
     * @param mixed $response відповідь makeApiRequest()
     */
    public static function of(array $errors, $response): string
    {
        if ($errors === []) {
            return is_array($response) && !empty($response['id']) ? self::SUCCESS : self::UNKNOWN;
        }

        $code = $errors['response']['code'] ?? '';
        if ($code === 'shift.not_opened') {
            return self::SHIFT_CLOSED;
        }
        if ($code === 'receipt.already_exists') {
            return self::ALREADY_EXISTS;
        }

        $http = (int) ($errors['http_code'] ?? 0);
        if ($http >= 400 && $http < 500 && !in_array($http, self::RETRYABLE_HTTP, true)) {
            return self::REJECTED;
        }

        return self::UNKNOWN;
    }
}
