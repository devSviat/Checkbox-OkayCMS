<?php

declare(strict_types=1);

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Набір джерел коштів, який магазин склав у налаштуваннях.
 *
 * Зберігається одним рядком у налаштуваннях
 * (`sviat__checkbox__advance_sources`), тому весь розбір зібраний тут і не
 * залежить ні від бази, ні від контейнера — це проста функція над рядком.
 *
 * Дві частини, бо це два різні питання. `names` — платіжні системи, які магазин
 * завів: вони лишаються записаними й тоді, коли вимкнені, інакше «вимкнути»
 * означало б «стерти й набирати знову». `enabled` — що з цього побачить
 * менеджер.
 *
 * Набір керує лише тим, що пропонується у списку. Чинність джерела лишається за
 * каталогом: ланцюжок, відкритий торік, і автоматика післяплати не мають
 * ламатися від того, що магазин прибрав пункт зі списку.
 */
final class CheckboxPaymentSources
{
    /**
     * @return array{enabled: string[], names: array<string, string[]>}
     */
    public static function decode(?string $raw): array
    {
        $decoded = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
        if (!is_array($decoded)) {
            return self::normalise([], []);
        }

        return self::normalise(
            is_array($decoded['enabled'] ?? null) ? $decoded['enabled'] : [],
            is_array($decoded['names'] ?? null) ? $decoded['names'] : []
        );
    }

    /**
     * @param array<array-key, mixed> $enabled ключі каталогу, які побачить менеджер
     * @param array<array-key, mixed> $names засіб платежу → заведені платіжні системи
     */
    public static function encode(array $enabled, array $names = []): string
    {
        return (string)json_encode(self::normalise($enabled, $names), JSON_UNESCAPED_UNICODE);
    }

    /**
     * Рядки сторінки налаштувань — по одному на засіб платежу з каталогу.
     *
     * Засіб із місцем під назву віддає не один перемикач, а список: NovaPay,
     * LiqPay і WayForPay — три записи одного шаблону, кожен зі своїм станом.
     *
     * @param array{enabled: string[], names: array<string, string[]>} $config
     * @return array<int, array{key: string, label: string, prefix: string, type: string, enabled: bool, editable: bool, names: array<int, array{name: string, on: bool}>, common: bool}>
     */
    public static function rows(array $config): array
    {
        $rows = [];
        foreach (CheckboxPaymentCatalogue::keys() as $base) {
            $editable = CheckboxPaymentCatalogue::needsName($base);

            $names = [];
            foreach ($config['names'][$base] ?? [] as $name) {
                $names[] = [
                    'name' => $name,
                    'on'   => in_array(CheckboxPaymentCatalogue::compose($base, $name), $config['enabled'], true),
                ];
            }

            $enabled = $editable
                ? (bool)array_filter(array_column($names, 'on'))
                : in_array($base, $config['enabled'], true);

            $rows[] = [
                'key'    => $base,
                'label'  => CheckboxPaymentCatalogue::defaultLabel($base),
                'prefix' => CheckboxPaymentCatalogue::labelPrefix($base),
                'type'   => CheckboxPaymentCatalogue::type($base),
                'enabled' => $enabled,
                // Правити можна лише те, без чого магазин не обійдеться, —
                // назву платіжної системи. Решту міток задає наказ № 601, і
                // поле вводу над ними означало б запрошення їх переписати.
                'editable' => $editable,
                'names'    => $names,
                // Заведене не ховаємо ніколи, навіть вимкнене й навіть у
                // рідкісній частині каталогу: інакше запис є, а знайти його ніде.
                'common' => $enabled || $names !== [] || in_array($base, CheckboxPaymentCatalogue::COMMON, true),
            ];
        }

        return $rows;
    }

    /**
     * Джерела для списку на картці замовлення.
     *
     * @param array{enabled: string[], names: array<string, string[]>} $config
     * @return array<int, array{key: string, label: string, type: string}>
     */
    public static function visible(array $config): array
    {
        $visible = [];
        foreach ($config['enabled'] as $key) {
            $visible[] = [
                'key'   => $key,
                'label' => CheckboxPaymentCatalogue::defaultLabel($key),
                'type'  => CheckboxPaymentCatalogue::type($key),
            ];
        }

        return $visible;
    }

    /**
     * @param array<array-key, mixed> $enabled сирі: з $_POST або з json_decode
     * @param array<array-key, mixed> $names
     * @return array{enabled: string[], names: array<string, string[]>}
     */
    private static function normalise(array $enabled, array $names): array
    {
        $cleanNames = [];
        foreach ($names as $base => $list) {
            if (!is_string($base) || !is_array($list) || !CheckboxPaymentCatalogue::needsName($base)) {
                continue;
            }
            foreach ($list as $name) {
                if (!is_string($name)) {
                    continue;
                }
                $name = trim($name);
                if ($name === ''
                    || in_array($name, $cleanNames[$base] ?? [], true)
                    || !CheckboxPaymentCatalogue::isKnown(CheckboxPaymentCatalogue::compose($base, $name))
                ) {
                    continue;
                }
                $cleanNames[$base][] = $name;
            }
        }

        $known = [];
        foreach ($enabled as $key) {
            if (!is_string($key) || !CheckboxPaymentCatalogue::isKnown($key) || in_array($key, $known, true)) {
                continue;
            }
            // Увімкнена платіжна система мусить бути й серед заведених: інакше
            // список на картці показував би те, чого в налаштуваннях не видно.
            $name = CheckboxPaymentCatalogue::name($key);
            if ($name !== null && !in_array($name, $cleanNames[CheckboxPaymentCatalogue::base($key)] ?? [], true)) {
                continue;
            }
            $known[] = $key;
        }

        // Порядок каталогу, а не порядок збереження: список джерел має виглядати
        // однаково скрізь, і жоден екран не сортує його сам. Назви одного засобу
        // лишаються в тому порядку, в якому їх вписали.
        $cleanEnabled = [];
        foreach (CheckboxPaymentCatalogue::keys() as $base) {
            foreach ($known as $key) {
                if (CheckboxPaymentCatalogue::base($key) === $base) {
                    $cleanEnabled[] = $key;
                }
            }
        }

        // Порожній набір лишив би менеджера без жодного способу провести аванс —
        // і без способу закрити вже відкритий ланцюжок. Це не конфігурація, це
        // глухий кут, тож замість нього стандартний набір.
        if (empty($cleanEnabled)) {
            $cleanEnabled = CheckboxPaymentCatalogue::DEFAULT_ENABLED;
        }

        return ['enabled' => $cleanEnabled, 'names' => $cleanNames];
    }
}
