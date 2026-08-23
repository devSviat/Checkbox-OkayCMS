<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Кожен ключ, який модуль просить у BackendTranslations, мусить бути в усіх
 * трьох мовних файлах.
 *
 * `getTranslation()` на відсутньому ключі віддає `false`, а не назву ключа. У
 * відповіді це `{"message": false}`, і `if(response.message)` у checkbox.js стає
 * хибним — фронт іде в гілку успіху й мовчки перезавантажує сторінку.
 */
class TranslationKeysTest extends TestCase
{
    private const LANGS = ['ua', 'ru', 'en'];

    public function testEveryRequestedKeyExistsInEveryLanguage(): void
    {
        $requested = self::requestedKeys();

        self::assertNotEmpty($requested, 'ключі не знайшлися — зламався сам збір, а не переклади');

        foreach (self::LANGS as $lang) {
            $defined = self::definedKeys($lang);
            foreach ($requested as $key => $file) {
                self::assertArrayHasKey(
                    $key,
                    $defined,
                    sprintf('%s: немає ключа %s, який просить %s', $lang, $key, $file)
                );
            }
        }
    }

    /** Порожній рядок у перекладі дасть той самий тихий reload, що й відсутній ключ. */
    public function testRequestedKeysAreNotEmptyStrings(): void
    {
        foreach (self::LANGS as $lang) {
            $defined = self::definedKeys($lang);
            foreach (array_keys(self::requestedKeys()) as $key) {
                if (!isset($defined[$key])) {
                    continue; // про це вже сказав тест вище
                }
                self::assertNotSame('', trim((string) $defined[$key]), "$lang: ключ $key порожній");
            }
        }
    }

    /**
     * @return array<string, string> ключ → файл, який його просить
     */
    private static function requestedKeys(): array
    {
        $keys = [];
        foreach (self::modulePhpFiles() as $file) {
            $source = (string) file_get_contents($file);
            if (preg_match_all('~getTranslation\(\s*[\'"]([\w]+)[\'"]~', $source, $matches)) {
                foreach ($matches[1] as $key) {
                    $keys[$key] = basename($file);
                }
            }
        }

        return $keys;
    }

    /** @return array<string, mixed> */
    private static function definedKeys(string $code): array
    {
        $file = self::moduleDir() . '/Backend/lang/' . $code . '.php';
        self::assertFileExists($file);

        // Мовний файл — послідовність присвоєнь у $lang, без return. Через
        // include, а не regex: так тест бачить рівно те, що побачить ядро.
        $lang = [];
        include $file;

        return is_array($lang) ? $lang : [];
    }

    /** @return list<string> */
    private static function modulePhpFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(self::moduleDir(), \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if (substr($path, -4) !== '.php') {
                continue;
            }
            // lang — джерело ключів, а не споживач. tests лежать усередині теки
            // модуля в репозиторії лаби.
            foreach (['lang', 'tests'] as $skip) {
                if (strpos($path, DIRECTORY_SEPARATOR . $skip . DIRECTORY_SEPARATOR) !== false) {
                    continue 2;
                }
            }
            $files[] = $path;
        }

        return $files;
    }

    /** Корінь модуля рахується від Init.php, щоб тест жив і тут, і в репозиторії лаби. */
    private static function moduleDir(): string
    {
        return dirname((string) (new ReflectionClass(Init::class))->getFileName(), 2);
    }
}
