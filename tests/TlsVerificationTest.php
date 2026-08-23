<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Запити до Checkbox несуть логін і пароль касира, Bearer-токен і ліцензійний
 * ключ, а CURLOPT_FOLLOWLOCATION понесе ті самі заголовки за редиректом.
 * Вимкнена перевірка сертифіката тут не помітна ніде: чеки пробиваються далі.
 *
 * Сторож читає вихідний код, бо самі опції curl після curl_setopt() назад не
 * читаються — curl_getinfo() їх не віддає.
 */
class TlsVerificationTest extends TestCase
{
    public function testCertificateVerificationIsNotDisabled(): void
    {
        $source = self::source();

        self::assertMatchesRegularExpression(
            '/CURLOPT_SSL_VERIFYPEER,\s*true/',
            $source,
            'перевірка сертифіката має бути ввімкнена явно'
        );
        self::assertMatchesRegularExpression(
            '/CURLOPT_SSL_VERIFYHOST,\s*2/',
            $source,
            'без VERIFYHOST=2 валідний сертифікат чужого домену пройде'
        );
    }

    /** @dataProvider disablingProvider */
    #[DataProvider('disablingProvider')]
    public function testNoOptionTurnsVerificationOff(string $option): void
    {
        self::assertDoesNotMatchRegularExpression(
            '/' . $option . ',\s*(false|0)\b/',
            self::source(),
            $option . ' не можна вимикати: у цих запитах їдуть облікові дані'
        );
    }

    public static function disablingProvider(): array
    {
        return [
            'peer' => ['CURLOPT_SSL_VERIFYPEER'],
            'host' => ['CURLOPT_SSL_VERIFYHOST'],
        ];
    }

    private static function source(): string
    {
        $file = (string) (new ReflectionClass(CheckboxApiHelper::class))->getFileName();

        return (string) file_get_contents($file);
    }
}
