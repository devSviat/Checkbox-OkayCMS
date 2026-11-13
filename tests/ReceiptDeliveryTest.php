<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptDelivery as Delivery;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Куди Checkbox надішле чек. Значення налаштування 0–4 збігаються з тими, що зберігав
 * попередній модуль OkCMS, тож уже збережене «1» лишається «на email».
 */
class ReceiptDeliveryTest extends TestCase
{
    private const string EMAIL = 'buyer@example.com';
    private const string PHONE = '+380671234567';

    /** @return iterable<string, array{int, ?string, ?string, ?array}> */
    public static function modes(): iterable
    {
        $email = ['emails' => [self::EMAIL]];
        $phone = ['phone' => '380671234567'];

        yield 'off' => [Delivery::NONE, self::EMAIL, self::PHONE, null];
        yield 'email' => [Delivery::EMAIL, self::EMAIL, self::PHONE, $email];
        yield 'email, order without email' => [Delivery::EMAIL, null, self::PHONE, null];
        yield 'phone' => [Delivery::PHONE, self::EMAIL, self::PHONE, $phone];
        yield 'phone, order without phone' => [Delivery::PHONE, self::EMAIL, null, null];
        yield 'both' => [Delivery::EMAIL_AND_PHONE, self::EMAIL, self::PHONE, $email + $phone];
        yield 'both, only phone known' => [Delivery::EMAIL_AND_PHONE, null, self::PHONE, $phone];
        yield 'email first: has email' => [Delivery::EMAIL_ELSE_PHONE, self::EMAIL, self::PHONE, $email];
        yield 'email first: no email, phone is used' => [Delivery::EMAIL_ELSE_PHONE, null, self::PHONE, $phone];
        yield 'email first: invalid email counts as none' => [Delivery::EMAIL_ELSE_PHONE, 'not-an-email', self::PHONE, $phone];
        yield 'email first: nothing known' => [Delivery::EMAIL_ELSE_PHONE, '', '', null];
        yield 'legacy 5 is not offered and sends nothing' => [5, self::EMAIL, self::PHONE, null];
    }

    #[DataProvider('modes')]
    public function testModeDecidesWhereTheReceiptGoes(int $mode, ?string $email, ?string $phone, ?array $expected): void
    {
        self::assertSame($expected, Delivery::payload($mode, $email, $phone));
    }

    /** @return iterable<string, array{?string, ?string}> */
    public static function phones(): iterable
    {
        yield 'stored E.164' => ['+380671234567', '380671234567'];
        yield 'typed with spaces' => ['+38 (067) 123-45-67', '380671234567'];
        yield 'local 0XX' => ['0671234567', '380671234567'];
        yield 'without plus' => ['380671234567', '380671234567'];
        yield 'foreign number: Checkbox delivers only to +380' => ['+48601234567', null];
        yield 'too short' => ['+38067123456', null];
        yield 'empty' => ['', null];
        yield 'null' => [null, null];
    }

    #[DataProvider('phones')]
    public function testPhoneIsNormalisedForCheckbox(?string $raw, ?string $expected): void
    {
        self::assertSame($expected, Delivery::phone($raw));
    }

    public function testEmailIsTrimmedAndValidated(): void
    {
        self::assertSame(['emails' => [self::EMAIL]], Delivery::payload(Delivery::EMAIL, '  ' . self::EMAIL . ' ', null));
    }

    public function testOnlyListedModesAreAccepted(): void
    {
        self::assertSame([0, 1, 2, 3, 4], Delivery::MODES);
        self::assertSame(Delivery::EMAIL_AND_PHONE, Delivery::mode('3'));
        self::assertSame(Delivery::NONE, Delivery::mode('5'));
        self::assertSame(Delivery::NONE, Delivery::mode(null));
        self::assertFalse(Delivery::usesPhone(Delivery::EMAIL));
        self::assertTrue(Delivery::usesPhone(Delivery::EMAIL_ELSE_PHONE));
    }

    /** @return iterable<string, array{array, mixed, ?array}> */
    public static function billing(): iterable
    {
        yield 'enabled in Checkbox' => [[], ['can_send_sms' => true, 'billing_enabled' => true, 'balance' => 37844], [true, 37844]];
        yield 'not enabled' => [[], ['can_send_sms' => false, 'billing_enabled' => false, 'balance' => 0], [false, 0]];
        yield 'request failed: unknown, not "off"' => [['message' => 'timed out'], false, null];
        yield 'unexpected body' => [[], ['detail' => 'x'], null];
    }

    /** Без увімкненої послуги Checkbox приймає номер і мовчки нічого не надсилає. */
    #[DataProvider('billing')]
    public function testPhoneDeliveryStatusComesFromCheckbox(array $errors, mixed $response, ?array $expected): void
    {
        $api = new class ($errors, $response) extends CheckboxApiHelper {
            public array $urls = [];
            public function __construct(private array $scriptedErrors, private mixed $scripted) { $this->accessToken = 'token'; }
            protected function makeApiRequest(string $url, array $params = [], array $requestParams = [])
            {
                $this->urls[] = ($requestParams['method'] ?? 'GET') . ' ' . $url;
                $this->errors = $this->scriptedErrors;
                return $this->scripted;
            }
            protected function logFailure(string $event, array $context = []): void {}
        };

        $status = $api->phoneDeliveryStatus();

        self::assertSame($expected, $status === null ? null : [$status->enabled, $status->balanceKopiyky]);
        self::assertSame(['GET organization/sms-billing'], $api->urls);
    }
}
