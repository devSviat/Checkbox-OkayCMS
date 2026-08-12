<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxApiHelper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Розбір відповіді фіскального API Checkbox. Це єдина точка, де модуль вирішує
 * «чек пройшов» чи «чек не пройшов». Хибно визнана успішною відповідь означає
 * непробитий чек при закритому замовленні — тобто розбіжність із ДПС.
 *
 * processApiResponse() уже protected і не робить жодного запиту — сам метод
 * чистий. Конструктор ходить у ServiceLocator, тож обходимо його.
 */
class CheckboxApiResponseTest extends TestCase
{
    private CheckboxApiHelper $helper;

    protected function setUp(): void
    {
        $this->helper = (new \ReflectionClass(CheckboxApiHelper::class))->newInstanceWithoutConstructor();
        $this->writeErrors([]);
    }

    private function writeErrors(array $errors): void
    {
        $reflected = new \ReflectionProperty(CheckboxApiHelper::class, 'errors');
        if (PHP_VERSION_ID < 80100) { $reflected->setAccessible(true); }
        $reflected->setValue($this->helper, $errors);
    }

    private function readErrors(): array
    {
        $reflected = new \ReflectionProperty(CheckboxApiHelper::class, 'errors');
        if (PHP_VERSION_ID < 80100) { $reflected->setAccessible(true); }
        return $reflected->getValue($this->helper);
    }

    private function process(string|false $data, array $curlInfo = []): mixed
    {
        $reflected = new \ReflectionMethod($this->helper, 'processApiResponse');
        if (PHP_VERSION_ID < 80100) { $reflected->setAccessible(true); }
        return $reflected->invoke($this->helper, $data, 'https://api.checkbox.ua/api/v1/receipts', [], $curlInfo, []);
    }

    // --- порожня відповідь --------------------------------------------------

    /** @dataProvider emptyResponseProvider */
    #[DataProvider('emptyResponseProvider')]
    public function testEmptyTransportResultIsAFailure(string|false $data): void
    {
        self::assertFalse($this->process($data));
        self::assertSame([], $this->readErrors());
    }

    public static function emptyResponseProvider(): array
    {
        return [
            'curl не відповів' => [false],
            'порожнє тіло'     => [''],
        ];
    }

    // --- бінарні типи -------------------------------------------------------

    /**
     * PDF чека і HTML-версія приходять як є — їх не можна проганяти через
     * json_decode, інакше друкована форма чека перетворилась би на помилку.
     */
    /** @dataProvider passthroughContentTypeProvider */
    #[DataProvider('passthroughContentTypeProvider')]
    public function testNonJsonContentTypesArePassedThroughUntouched(string $contentType): void
    {
        $body = '%PDF-1.4 не json';

        self::assertSame($body, $this->process($body, ['content_type' => $contentType]));
        self::assertSame([], $this->readErrors());
    }

    public static function passthroughContentTypeProvider(): array
    {
        return [
            'html'          => ['text/html; charset=utf-8'],
            'plain'         => ['text/plain; charset=utf-8'],
            'pdf'           => ['application/pdf'],
            'pdf із хвостом' => ['application/pdf; charset=binary'],
        ];
    }

    // --- зіпсований JSON ----------------------------------------------------

    /**
     * Балансувальник між нами й API цілком може віддати HTML-сторінку помилки з
     * заголовком application/json. Це мусить бути провал із записаною причиною,
     * а не мовчазний null.
     */
    public function testBrokenJsonIsAFailureWithAReason(): void
    {
        self::assertFalse($this->process('<html>502 Bad Gateway</html>', ['content_type' => 'application/json']));

        $errors = $this->readErrors();
        self::assertArrayHasKey('message', $errors);
        self::assertStringContainsString('JSON decode error', $errors['message']);
    }

    // --- HTTP-помилки -------------------------------------------------------

    public function testHttpErrorIsRecordedWithCodeAndRequestContext(): void
    {
        $this->process('{"message":"Shift is not opened"}', ['http_code' => 400, 'content_type' => 'application/json']);

        $errors = $this->readErrors();
        self::assertSame('Shift is not opened', $errors['message']);
        self::assertSame(400, $errors['http_code']);
        self::assertSame('https://api.checkbox.ua/api/v1/receipts', $errors['requestData']['url']);
    }

    /**
     * Помилка валідації Checkbox лежить у detail[0].msg і сама по собі
     * інформативніша за загальне message — вона дописується, а не губиться.
     */
    public function testValidationDetailIsAppendedToTheMessage(): void
    {
        $body = '{"message":"Validation error","detail":[{"msg":"goods.0.price must be positive"}]}';

        $this->process($body, ['http_code' => 422, 'content_type' => 'application/json']);

        self::assertSame(
            'Validation error: goods.0.price must be positive',
            $this->readErrors()['message']
        );
    }

    /** Без message у тілі лишається хоча б код — інакше в лозі був би порожній рядок. */
    public function testHttpErrorWithoutMessageFallsBackToTheStatusCode(): void
    {
        $this->process('{}', ['http_code' => 503, 'content_type' => 'application/json']);

        self::assertSame('HTTP Error 503', $this->readErrors()['message']);
    }

    /** Нечисловий http_code не має ламати розбір — вважаємо запит успішним. */
    public function testNonNumericHttpCodeIsTreatedAsSuccess(): void
    {
        $result = $this->process('{"id":"abc"}', ['http_code' => 'сміття', 'content_type' => 'application/json']);

        self::assertSame(['id' => 'abc'], $result);
        self::assertSame([], $this->readErrors());
    }

    // --- бізнес-помилки при HTTP 200 ----------------------------------------

    /**
     * Checkbox уміє відповідати 200 із текстом помилки в message. Ознака успіху —
     * наявність id (чек створено) або access_token (логін пройшов). Якщо є
     * message і немає жодного маркера — це відмова, попри код 200.
     */
    public function testBusinessErrorAtHttp200IsRecorded(): void
    {
        $result = $this->process('{"message":"Cashier is not authorized"}', ['content_type' => 'application/json']);

        self::assertSame(['message' => 'Cashier is not authorized'], $result);
        self::assertSame('Cashier is not authorized', $this->readErrors()['message']);
    }

    /** @dataProvider successMarkerProvider */
    #[DataProvider('successMarkerProvider')]
    public function testMessageAlongsideASuccessMarkerIsNotAnError(string $body): void
    {
        $this->process($body, ['content_type' => 'application/json']);

        self::assertSame([], $this->readErrors());
    }

    public static function successMarkerProvider(): array
    {
        return [
            'чек створено'   => ['{"id":"f1e2d3","message":"created"}'],
            'логін пройшов'  => ['{"access_token":"tok","message":"ok"}'],
        ];
    }

    public function testCleanSuccessResponseIsDecodedAndLeavesNoErrors(): void
    {
        $result = $this->process('{"id":"f1e2d3","status":"DONE"}', ['content_type' => 'application/json']);

        self::assertSame(['id' => 'f1e2d3', 'status' => 'DONE'], $result);
        self::assertSame([], $this->readErrors());
    }
}
