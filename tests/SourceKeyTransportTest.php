<?php

namespace Modules\Sviat\Checkbox;

use Okay\Core\Request;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentCatalogue;
use PHPUnit\Framework\TestCase;

/**
 * Ключ джерела їде з картки замовлення в ajax-ендпоінт звичайним POST.
 *
 * `Request::post($name, 'string')` проганяє значення через
 * `preg_replace('/[^\p{L}\p{Nd}\d\s_\-.%]/ui', '')`, а двокрапки в білому
 * списку немає — «integrator:NovaPay» перетворюється на «integratorNovaPay».
 * Помилка тиха: назовні йде «Невідоме джерело коштів», і аванс не проходить для
 * жодної названої платіжної системи.
 *
 * Захистом тут мусить бути білий список каталогу, а не фільтр рядка.
 */
class SourceKeyTransportTest extends TestCase
{
    /** @return array<string, array{0: string}> */
    public static function sourceKeyProvider(): array
    {
        return [
            'простий ключ'  => ['cash'],
            'з підкресленням' => ['bank_account'],
            'з назвою системи' => ['integrator:NovaPay'],
            'назва з пробілом' => ['integrator:Mono Pay'],
            'криптовалюта'  => ['crypto:USDT'],
        ];
    }

    /**
     * @dataProvider sourceKeyProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('sourceKeyProvider')]
    public function testSourceKeyArrivesIntact(string $key): void
    {
        $_POST = ['source' => $key];
        $request = new Request();

        self::assertSame($key, $request->post('source'));
        self::assertTrue(CheckboxPaymentCatalogue::isKnown($key), $key);
    }

    /**
     * Контроль до попереднього тесту: без нього «читаємо сире значення» — просто
     * слова, і наступна правка спокійно поверне сюди фільтр.
     */
    public function testStringFilterWouldBreakTheKey(): void
    {
        $_POST = ['source' => 'integrator:NovaPay'];
        $request = new Request();

        self::assertSame('integratorNovaPay', $request->post('source', 'string'));
        self::assertFalse(CheckboxPaymentCatalogue::isKnown('integratorNovaPay'));
    }

    /**
     * Пильнуємо саме місце виклику, а не лише поведінку ядра.
     *
     * Тести вище показують, що фільтр ламає ключ, але жоден із них не червоніє,
     * якщо `'string'` повернути в контролер: помилка видна лише в готовому
     * запиті. Тому перевіряємо вихідний код — так само, як TranslationKeysTest
     * стереже ключі перекладів.
     */
    public function testControllerReadsTheSourceKeyRaw(): void
    {
        foreach (self::modulePhpFiles() as $file) {
            $source = (string)file_get_contents($file);
            self::assertDoesNotMatchRegularExpression(
                '~post\(\s*[\'"]source[\'"]\s*,~',
                $source,
                basename($file) . ': ключ джерела читається з типом — фільтр вирізає двокрапку'
            );
        }
    }

    /** @return list<string> */
    private static function modulePhpFiles(): array
    {
        $dir = dirname((new \ReflectionClass(CheckboxPaymentCatalogue::class))->getFileName(), 2);
        $files = [];
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );
        foreach ($iterator as $entry) {
            $path = $entry->getPathname();
            if (substr($path, -4) !== '.php') {
                continue;
            }
            // У репозиторії лаби тести лежать усередині теки модуля, і цей файл
            // сам містить взірцевий виклик із фільтром — без винятку перевірка
            // ловила б власний текст.
            if (strpos($path, DIRECTORY_SEPARATOR . 'tests' . DIRECTORY_SEPARATOR) !== false) {
                continue;
            }
            $files[] = $path;
        }

        self::assertNotEmpty($files, 'файли модуля не знайшлися — зламався сам збір');

        return $files;
    }

    /** Кожен ключ каталогу має пережити дорогу без фільтра. */
    public function testEveryCatalogueKeySurvives(): void
    {
        foreach (CheckboxPaymentCatalogue::keys() as $base) {
            $key = CheckboxPaymentCatalogue::needsName($base)
                ? CheckboxPaymentCatalogue::compose($base, 'TestPay')
                : $base;

            $_POST = ['source' => $key];
            self::assertSame($key, (new Request())->post('source'), $base);
        }
    }
}
