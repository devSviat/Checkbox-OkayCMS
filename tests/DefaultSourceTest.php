<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\BackendPostRedirectGet;
use Okay\Core\BackendTranslations;
use Okay\Core\Design;
use Okay\Core\EntityFactory;
use Okay\Core\Modules\EntityField;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Entities\OrdersEntity;
use Okay\Entities\PaymentsEntity;
use Okay\Modules\Sviat\Checkbox\Backend\Controllers\CheckboxAdmin;
use Okay\Modules\Sviat\Checkbox\Entities\FiscalReceiptsEntity;
use Okay\Modules\Sviat\Checkbox\Extenders\BackendExtender;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPaymentSources;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxPrepaymentHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxReceiptsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxShiftsHelper;
use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxSourceChoice;
use Okay\Modules\Sviat\Checkbox\Init\Init;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * Джерело коштів у формах авансу й післяплати обирається наперед: аванс — за
 * налаштуванням магазину, ручна післяплата — тим, яким у замовленні прийшов
 * аванс. Підставлене значення лише видно у формі; автоматичний чек при
 * отриманні посилки й далі бере джерело зі способу оплати.
 */
class DefaultSourceTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox';

    private ?string $compileDir = null;

    protected function tearDown(): void
    {
        if ($this->compileDir !== null && is_dir($this->compileDir)) {
            array_map('unlink', glob($this->compileDir . '/*') ?: []);
            rmdir($this->compileDir);
        }
        $_POST = [];
    }

    private static function sourcesSetting(): string
    {
        return CheckboxPaymentSources::encode(
            ['cash', 'bank_account', 'integrator:NovaPay', 'integrator:LiqPay'],
            ['integrator' => ['NovaPay', 'LiqPay']]
        );
    }

    /** @return array<int, array{key: string, label: string, type: string}> */
    private static function sources(): array
    {
        return CheckboxPaymentSources::visible(CheckboxPaymentSources::decode(self::sourcesSetting()));
    }

    public function testChoiceTakesFirstCandidateThatIsStillConfigured(): void
    {
        self::assertSame('integrator:LiqPay', CheckboxSourceChoice::pick(self::sources(), 'integrator:LiqPay'));
        self::assertSame('cash', CheckboxSourceChoice::pick(self::sources(), 'integrator:WayForPay', null, '', 'cash'));
        self::assertSame('', CheckboxSourceChoice::pick(self::sources(), 'integrator:WayForPay'), 'вимкнене джерело не підставляється');
        self::assertSame('', CheckboxSourceChoice::pick(self::sources(), null, '', ['cash']));
    }

    // --- Налаштування -------------------------------------------------------

    public function testDefaultSourceIsSavedWhenConfigured(): void
    {
        $saved = $this->saveSettings('integrator:NovaPay');

        self::assertSame('integrator:NovaPay', $saved[Init::DEFAULT_SOURCE], 'двокрапка в ключі мусить дожити до налаштувань');
    }

    public function testDefaultSourceOutsideConfiguredSetIsDropped(): void
    {
        self::assertSame('', $this->saveSettings('internet_banking')[Init::DEFAULT_SOURCE], 'відомий каталогу, але вимкнений');
        self::assertSame('', $this->saveSettings('integrator:Невідома')[Init::DEFAULT_SOURCE]);
        self::assertSame('', $this->saveSettings('<script>')[Init::DEFAULT_SOURCE]);
        self::assertSame('', $this->saveSettings(null)[Init::DEFAULT_SOURCE], '«без джерела» — теж вибір');
    }

    /** Вимкнути джерело й зробити його типовим одним збереженням не можна. */
    public function testDefaultSourceIsCheckedAgainstTheSetSavedTogether(): void
    {
        $saved = $this->saveSettings('integrator:NovaPay', ['integrator' => [0 => '1']], ['integrator' => [0 => 'LiqPay']]);

        self::assertSame('', $saved[Init::DEFAULT_SOURCE]);
    }

    /**
     * @param array<string, mixed>|null $switches
     * @param array<string, mixed>|null $names
     * @return array<string, mixed>
     */
    private function saveSettings(?string $default, ?array $switches = null, ?array $names = null): array
    {
        $_POST = [
            'sviat__checkbox__source_enabled' => ['cash' => '1', 'bank_account' => '1'],
            'sviat__checkbox__source_name'    => $names ?? ['integrator' => [0 => 'NovaPay', 1 => 'LiqPay']],
            'sviat__checkbox__source_on'      => $switches ?? ['integrator' => [0 => '1', 1 => '1']],
        ];
        if ($default !== null) {
            $_POST[Init::DEFAULT_SOURCE] = $default;
        }
        $request = new Request();

        $saved = new \ArrayObject();
        $settings = $this->createStub(Settings::class);
        $settings->method('set')->willReturnCallback(function ($name, $value) use ($saved) {
            $saved[$name] = $value;
        });

        $controller = new ReflectionClass(CheckboxAdmin::class)->newInstanceWithoutConstructor();
        Closure::bind(function () use ($request, $settings) {
            $this->request = $request;
            $this->settings = $settings;
            $this->saveDefaultSource($this->saveAdvanceSources());
        }, $controller, CheckboxAdmin::class)();

        return $saved->getArrayCopy();
    }

    public function testSettingsPageOffersConfiguredSourcesAndNone(): void
    {
        $template = (string)file_get_contents(self::MODULE . '/Backend/design/html/checkbox_admin.tpl');

        self::assertStringContainsString('name="' . Init::DEFAULT_SOURCE . '"', $template);
        $html = $this->renderSelect($template, 'name="' . Init::DEFAULT_SOURCE . '"', [
            'checkbox_default_sources' => self::sources(),
            'checkbox_default_source'  => 'integrator:LiqPay',
        ]);

        self::assertMatchesRegularExpression('~<option value="">[^<]+</option>~u', $html);
        self::assertStringContainsString('<option value="integrator:LiqPay" selected>', $html);
        self::assertSame(1, substr_count($html, ' selected'));

        $evil = $this->renderSelect($template, 'name="' . Init::DEFAULT_SOURCE . '"', [
            'checkbox_default_sources' => [['key' => 'x"><b>', 'label' => '<i>Мітка</i>', 'type' => 'CASH']],
            'checkbox_default_source'  => '',
        ]);
        self::assertStringNotContainsString('<b>', $evil);
        self::assertStringNotContainsString('<i>', $evil);
    }

    // --- Форми в картці замовлення ------------------------------------------

    public function testAdvanceFormOpensWithDefaultSource(): void
    {
        $html = $this->renderOrderSelect('fn-checkbox-advance-source', ['checkboxAdvanceSource' => 'integrator:NovaPay']);

        self::assertMatchesRegularExpression('~^\s*<option value="">~u', substr($html, strpos($html, '>') + 1), 'порожній пункт лишається першим');
        self::assertStringContainsString('<option value="integrator:NovaPay" selected>', $html);
        self::assertSame(1, substr_count($html, ' selected'));
    }

    public function testAdvanceFormWithoutDefaultSelectsNothing(): void
    {
        $html = $this->renderOrderSelect('fn-checkbox-advance-source', ['checkboxAdvanceSource' => '']);

        self::assertStringNotContainsString(' selected', $html);
    }

    public function testAfterPaymentFormSelectsPreparedSource(): void
    {
        $html = $this->renderOrderSelect('fn-checkbox-after-payment-source', ['checkboxAfterPaymentSource' => 'integrator:LiqPay']);

        self::assertStringContainsString('<option value="integrator:LiqPay" selected>', $html);
        self::assertSame(1, substr_count($html, ' selected'));
    }

    /** Ключ і мітка — дані з налаштувань, адмінка сама нічого не екранує. */
    public function testOrderFormsEscapeSources(): void
    {
        $evil = [['key' => 'x"><b>', 'label' => '<i>Мітка</i>', 'type' => 'CASH']];
        foreach (['fn-checkbox-advance-source' => 'checkboxAdvanceSource', 'fn-checkbox-after-payment-source' => 'checkboxAfterPaymentSource'] as $class => $var) {
            $html = $this->renderOrderSelect($class, ['checkboxSources' => $evil, $var => 'x"><b>']);

            self::assertStringNotContainsString('<b>', $html, $class);
            self::assertStringNotContainsString('<i>', $html, $class);
            self::assertStringContainsString(' selected', $html, $class);
        }
    }

    /** @param array<string, mixed> $vars */
    private function renderOrderSelect(string $class, array $vars): string
    {
        $template = (string)file_get_contents(self::MODULE . '/Backend/design/html/fiscal_receipt_order_block.tpl');

        return $this->renderSelect($template, $class, $vars + ['checkboxSources' => self::sources()]);
    }

    /** @param array<string, mixed> $vars */
    private function renderSelect(string $template, string $needle, array $vars): string
    {
        $at = strpos($template, $needle);
        self::assertIsInt($at, $needle);
        $start = (int)strrpos(substr($template, 0, $at), '<select');
        $block = substr($template, $start, (int)strpos($template, '</select>', $at) + strlen('</select>') - $start);

        if ($this->compileDir === null) {
            $this->compileDir = sys_get_temp_dir() . '/okay-checkbox-source-' . bin2hex(random_bytes(4));
            mkdir($this->compileDir);
        }
        // Сток 4.5.3 має Smarty 4 (клас \Smarty), форк — Smarty 5 (\Smarty\Smarty); потрібні методи однакові.
        $class = class_exists('Smarty\\Smarty') ? 'Smarty\\Smarty' : 'Smarty';
        $smarty = new $class();
        $smarty->setCompileDir($this->compileDir);
        $smarty->assign('btr', (object)[
            'sviat__checkbox__advance_source_label' => 'Джерело',
            'sviat__checkbox__advance_source_empty' => '— оберіть —',
            'sviat__checkbox__default_source'       => 'Типове джерело',
            'sviat__checkbox__default_source_none'  => 'Без джерела',
        ]);
        foreach ($vars as $name => $value) {
            $smarty->assign($name, $value);
        }

        return $smarty->fetch('string:' . $block);
    }

    // --- Картка замовлення: що підставляється -------------------------------

    public function testCardPreparesDefaultSourceForAdvance(): void
    {
        $assigned = $this->card([], 'integrator:LiqPay');

        self::assertSame('integrator:LiqPay', $assigned['checkboxAdvanceSource']);
    }

    public function testCardIgnoresDefaultThatIsNoLongerConfigured(): void
    {
        $assigned = $this->card([], 'internet_banking');

        self::assertSame('', $assigned['checkboxAdvanceSource']);
    }

    public function testAfterPaymentPreselectsSourceOfTheAdvance(): void
    {
        $assigned = $this->card([$this->chainRow('integrator:LiqPay')], 'cash', 'bank_account');

        self::assertSame('integrator:LiqPay', $assigned['checkboxAfterPaymentSource']);
    }

    /** Джерело аванса беремо з рядка цього ланцюжка, а не з пізнішого рядка часткової оплати. */
    public function testAfterPaymentReadsTheAdvanceRowOfCurrentChain(): void
    {
        $partial = $this->chainRow(null);
        $partial->id = 2;
        $old = $this->chainRow('bank_account');
        $old->id = 0;
        $old->relation_id = 'CHAIN-OLD';
        $old->chain_status = 'CANCELLED';

        $assigned = $this->card([$old, $this->chainRow('integrator:LiqPay'), $partial], 'cash', 'bank_account');

        self::assertSame('integrator:LiqPay', $assigned['checkboxAfterPaymentSource']);
    }

    public function testRemovedAdvanceSourceFallsBackToDefault(): void
    {
        $assigned = $this->card([$this->chainRow('integrator:WayForPay')], 'cash', 'bank_account');

        self::assertSame('cash', $assigned['checkboxAfterPaymentSource']);
    }

    public function testOldAdvanceWithoutSourceFallsBackToDefault(): void
    {
        $assigned = $this->card([$this->chainRow(null)], 'integrator:NovaPay', 'bank_account');

        self::assertSame('integrator:NovaPay', $assigned['checkboxAfterPaymentSource']);
    }

    /** Без джерела аванса й без типового — як до 1.7.0: зі способу оплати, інакше перше в списку. */
    public function testWithoutAdvanceSourceAndDefaultPaymentMethodDecides(): void
    {
        self::assertSame('bank_account', $this->card([$this->chainRow(null)], '', 'bank_account')['checkboxAfterPaymentSource']);
        self::assertSame('cash', $this->card([$this->chainRow(null)], null, 'internet_banking')['checkboxAfterPaymentSource']);
    }

    private function chainRow(?string $source): object
    {
        return (object)[
            'id' => 1, 'order_id' => 7, 'receipt_id' => 'advance', 'receipt_type' => 'prepayment',
            'relation_id' => 'CHAIN-1', 'chain_status' => 'PARTIAL_PAID', 'chain_paid_sum' => 10000,
            'chain_total_sum' => 30000, 'is_return' => 0, 'is_test' => 0, 'related_receipt_id' => null,
            'payment_source' => $source,
        ];
    }

    /**
     * @param list<object> $rows
     * @return array<string, mixed>
     */
    private function card(array $rows, ?string $default, string $orderPaymentSource = 'bank_account'): array
    {
        $receipts = new class ($rows) {
            public function __construct(public array $rows) {}
            public function order($o = null, array $a = []) { return $this; }
            public function find(array $f = []) { return $this->rows; }
        };
        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn ($c) => [FiscalReceiptsEntity::class => $receipts][$c] ?? null);

        $settingValues = [Init::ADVANCE_SOURCES => self::sourcesSetting(), Init::DEFAULT_SOURCE => $default];
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(fn ($name) => $settingValues[$name] ?? null);

        $chain = null;
        foreach ($rows as $row) {
            if ($row->chain_status === 'PARTIAL_PAID') {
                $chain = ['pre_payment_status' => 'PARTIAL_PAID', 'relation_id' => $row->relation_id, 'paid_sum' => 10000, 'total_sum' => 30000, 'left_to_pay' => 20000];
            }
        }
        $helper = $this->createStub(CheckboxHelper::class);
        $helper->method('orderChainStatus')->willReturn($chain);
        $helper->method('orderPaymentSource')->willReturn($orderPaymentSource);

        $assigned = new \ArrayObject();
        $design = $this->createStub(Design::class);
        $design->method('assign')->willReturnCallback(function ($name, $value) use ($assigned) {
            $assigned[$name] = $value;
        });
        $translations = $this->createStub(BackendTranslations::class);
        $translations->method('getTranslation')->willReturnArgument(0);

        $extender = new BackendExtender(
            $design,
            new Request(),
            $settings,
            $factory,
            $helper,
            $translations,
            new class extends BackendPostRedirectGet { public function __construct() {} public function __destruct() {} }
        );
        $extender->initializeOrderReceipts((object)['id' => 7, 'payment_method_id' => null, 'paid' => 0], 7);

        return $assigned->getArrayCopy();
    }

    // --- Запис джерела в рядок ланцюжка -------------------------------------

    #[RunInSeparateProcess]
    public function testAdvanceSourceIsSavedOnTheChainRow(): void
    {
        FiscalReceiptsEntity::addField(FiscalReceiptsEntity::PAYMENT_SOURCE_FIELD);
        $env = $this->scripted([[[], ['id' => 'advance', 'pre_payment_relation_id' => 'CHAIN-1']]]);

        $this->openChain($env, 'integrator:NovaPay');

        self::assertCount(1, $env->entity->rows);
        self::assertSame('advance', $env->entity->rows[0]->receipt_id);
        self::assertSame('integrator:NovaPay', $env->entity->rows[0]->payment_source);
    }

    /** Колонки ще немає (модуль не оновлено) — аванс проходить, як і раніше, лише без джерела. */
    public function testAdvanceWithoutColumnWritesNoSource(): void
    {
        self::assertNotContains(FiscalReceiptsEntity::PAYMENT_SOURCE_FIELD, FiscalReceiptsEntity::getFields());
        $env = $this->scripted([[[], ['id' => 'advance', 'pre_payment_relation_id' => 'CHAIN-1']]]);

        $this->openChain($env, 'integrator:NovaPay');

        self::assertCount(1, $env->entity->rows);
        self::assertSame('advance', $env->entity->rows[0]->receipt_id);
        self::assertSame([], $env->entity->writesWith(FiscalReceiptsEntity::PAYMENT_SOURCE_FIELD));
    }

    /**
     * Контроль: автоматичний чек при отриманні посилки джерело авансу й типове
     * не бачить — назву засобу платежу дає спосіб оплати замовлення.
     */
    #[RunInSeparateProcess]
    public function testAutomaticAfterPaymentKeepsOrderPaymentSource(): void
    {
        FiscalReceiptsEntity::addField(FiscalReceiptsEntity::PAYMENT_SOURCE_FIELD);
        $partly = [[], ['pre_payment_status' => 'PARTIAL_PAID', 'paid_sum' => 10000, 'total_sum' => 30000]];
        $env = $this->scripted(
            [$partly, $partly, [[], ['id' => 'final']], [[], ['pre_payment_status' => 'FULL_PAID', 'paid_sum' => 30000, 'total_sum' => 30000]]],
            [$this->chainRow('integrator:LiqPay')]
        );
        $env->settings[Init::DEFAULT_SOURCE] = 'cash';
        $env->orders->order = (object)['id' => 7, 'payment_method_id' => 3, 'paid' => 1];
        $env->payments->method = (object)[
            'id' => 3,
            Init::PAYMENT_LABEL_FIELD => 'Платіж NovaPay',
            Init::PAYMENT_TYPE_FIELD  => 'CASHLESS',
        ];

        $env->helper->fiscaliseOrder(7);

        $posts = array_values(array_filter($env->helper->payloads, fn ($p) => isset($p['payments'])));
        self::assertCount(1, $posts);
        self::assertSame(['type' => 'CASHLESS', 'label' => 'Платіж NovaPay', 'value' => 20000], $posts[0]['payments'][0]);
    }

    private function openChain(object $env, string $source)
    {
        $context = ['cashierName' => 'Каса', 'goods' => [], 'footer' => '', 'delivery' => null, 'goodsTotal' => 30000];

        return new ReflectionMethod(CheckboxReceiptsHelper::class, 'openChain')->invoke($env->helper, 7, 10000, $source, $context);
    }

    /** @param list<array{0: array, 1: mixed}> $script відповіді API по черзі: [errors, response] */
    private function scripted(array $script, array $rows = []): object
    {
        $entity = new class ($rows) {
            public int $nextId = 100;
            /** @var list<array<string, mixed>> */
            public array $writes = [];
            public function __construct(public array $rows) {}
            public function order($o = null, array $a = []) { return $this; }
            public function find(array $f = []) { return $this->rows; }
            public function findOne(array $f = [])
            {
                foreach (array_reverse($this->rows) as $r) {
                    $ok = true;
                    foreach ($f as $k => $v) {
                        $ok = $ok && (string)($r->$k ?? '') === (string)$v;
                    }
                    if ($ok) {
                        return $r;
                    }
                }
                return null;
            }
            public function count(array $f = []) { return count(array_filter($this->rows, fn ($r) => $r->receipt_type === ($f['receipt_type'] ?? $r->receipt_type))); }
            public function add($o) { $o = (object)$o; $this->writes[] = (array)$o; $o->id = $this->nextId++; $o->receipt_id ??= ''; $this->rows[] = $o; return $o->id; }
            public function update($id, $o) { $this->writes[] = (array)$o; foreach ($this->rows as $r) { if ((int)$r->id === (int)$id) { foreach ((array)$o as $k => $v) { $r->$k = $v; } } } return $id; }
            public function delete($id) { $this->rows = array_values(array_filter($this->rows, fn ($r) => (int)$r->id !== (int)$id)); }
            /** @return list<array<string, mixed>> */
            public function writesWith(string $field): array { return array_values(array_filter($this->writes, fn ($w) => array_key_exists($field, $w))); }
        };
        $orders = new class {
            public ?object $order = null;
            public function get($id) { return $this->order; }
        };
        $payments = new class {
            public ?object $method = null;
            public function findOne(array $f = []) { return $this->method; }
        };

        $factory = $this->createStub(EntityFactory::class);
        $factory->method('get')->willReturnCallback(fn ($c) => [
            FiscalReceiptsEntity::class => $entity,
            OrdersEntity::class => $orders,
            PaymentsEntity::class => $payments,
        ][$c] ?? null);

        $translations = $this->createStub(BackendTranslations::class);
        $translations->method('getTranslation')->willReturnArgument(0);

        $shifts = new class extends CheckboxShiftsHelper {
            public function __construct() {}
            public function openShiftIfNeeded(int $maxWaitAttempts = 5, int $waitSeconds = 2): ?object { return (object)['status' => 'OPENED']; }
            public function getCashierInfo() { return ['full_name' => 'Каса']; }
        };

        $helper = new class ($script) extends CheckboxReceiptsHelper {
            public array $payloads = [];
            public function __construct(public array $script) {}
            protected function makeApiRequest(string $url, array $params = [], array $requestParams = [])
            {
                $this->payloads[] = $params;
                [$errors, $response] = array_shift($this->script);
                $this->errors = $errors;
                return $response;
            }
            public function call(string $url, array $params, string $method) { return $this->makeApiRequest($url, $params, ['method' => $method]); }
            protected function acquireOrderLock(int $orderId): bool { return true; }
            protected function releaseOrderLock(int $orderId): void {}
            public function getAccessToken() { return []; }
            protected function logFailure(string $event, array $context = []): void {}
        };

        $prepayment = new class ($helper) extends CheckboxPrepaymentHelper {
            public function __construct(private object $api) {}
            public function createChain(array $payload) { return $this->api->call('prepayment-receipts', $payload, 'POST'); }
            public function addPayment(string $relationId, array $payload) { return $this->api->call("prepayment-receipts/$relationId", $payload, 'POST'); }
            public function chainStatus(string $relationId): ?array
            {
                $response = $this->api->call("prepayment-receipts/$relationId", [], 'GET');
                return is_array($response) && isset($response['pre_payment_status']) ? $response : null;
            }
        };

        $settingValues = new \ArrayObject([Init::ADVANCE_SOURCES => self::sourcesSetting()]);
        $settings = $this->createStub(Settings::class);
        $settings->method('get')->willReturnCallback(fn ($name) => $settingValues[$name] ?? null);

        Closure::bind(function () use ($factory, $translations, $shifts, $prepayment, $settings) {
            $this->entityFactory = $factory;
            $this->translations = $translations;
            $this->settings = $settings;
            $this->accessToken = 'token';
            $this->shiftsHelper = $shifts;
            $this->prepaymentHelper = $prepayment;
            $this->receiptsTableReadable = true;
        }, $helper, CheckboxReceiptsHelper::class)();

        return (object)['helper' => $helper, 'entity' => $entity, 'orders' => $orders, 'payments' => $payments, 'settings' => $settingValues];
    }

    // --- Міграція 1.7.0 -----------------------------------------------------

    /** Колонку entity читає лише після оновлення: до нього SELECT упав би, і картка втратила б усі чеки. */
    public function testSourceColumnIsNotSelectedBeforeTheUpdate(): void
    {
        self::assertNotContains(FiscalReceiptsEntity::PAYMENT_SOURCE_FIELD, FiscalReceiptsEntity::getFields());

        $field = Init::paymentSourceColumn();
        self::assertSame(FiscalReceiptsEntity::PAYMENT_SOURCE_FIELD, $field->getName());
        self::assertTrue($field->isNullable(), 'наявні аванси лишаються NULL');
        self::assertSame('varchar(255)', $field->getType());
    }

    /** Ядро ковтає помилку ALTER: прапорець, що вмикає читання колонки, ставиться лише коли таблиця її має. */
    public function testColumnFlagOnlyWhenTheTableHasIt(): void
    {
        $migrated = [];
        $flagged = 0;
        $migrate = static function (EntityField $field) use (&$migrated): void {
            $migrated[] = $field->getName();
        };
        $flag = static function () use (&$flagged): void {
            $flagged++;
        };

        self::assertFalse(Init::addPaymentSourceColumnWith($migrate, static fn(): array => ['id', 'order_id'], $flag), 'ALTER не пройшов');
        self::assertSame(0, $flagged);

        self::assertTrue(Init::addPaymentSourceColumnWith($migrate, static fn(): array => ['id', 'order_id', 'payment_source'], $flag));
        self::assertTrue(Init::addPaymentSourceColumnWith($migrate, static fn(): array => ['id', 'order_id', 'payment_source'], $flag), 'повторний запуск безпечний');
        self::assertSame(2, $flagged);
        self::assertSame(['payment_source', 'payment_source', 'payment_source'], $migrated);
    }

    public function testUpdateMethodMatchesModuleVersion(): void
    {
        $manifest = json_decode((string)file_get_contents(self::MODULE . '/Init/module.json'), true);

        self::assertTrue(method_exists(Init::class, 'update_1_7_0'));
        // Міграція 1.7.0 має запуститися на будь-якій пізнішій версії модуля.
        self::assertTrue(version_compare($manifest['version'], '1.7.0', '>='), $manifest['version']);
    }

    public function testInitRegistersColumnOnlyAfterTheUpdate(): void
    {
        $source = (string)file_get_contents(self::MODULE . '/Init/Init.php');
        $init = substr($source, (int)strpos($source, 'public function init()'));

        self::assertSame(1, preg_match(
            '~if \(\$settings->get\(FiscalReceiptsEntity::PAYMENT_SOURCE_READY\)\) \{\s*\$this->registerEntityField\(FiscalReceiptsEntity::class, FiscalReceiptsEntity::PAYMENT_SOURCE_FIELD\);~',
            $init
        ), 'init() реєструє колонку лише за прапорцем оновлення');
    }

    public function testTextsExistInEveryLanguage(): void
    {
        foreach (['ua', 'en', 'ru'] as $language) {
            $lang = [];
            require self::MODULE . "/Backend/lang/$language.php";

            foreach (['default_source', 'default_source_none', 'default_source_hint'] as $key) {
                self::assertNotEmpty($lang['sviat__checkbox__' . $key] ?? null, "$language: $key");
            }
        }
    }
}
