<?php

namespace Modules\Sviat\Checkbox;

use Closure;
use Okay\Core\Request;
use Okay\Core\Settings;
use Okay\Modules\Sviat\Checkbox\Backend\Controllers\CheckboxAdmin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * Форма джерел коштів надсилає назву платіжної системи й окремо її вмикач.
 * Знятий прапорець браузер не надсилає взагалі, тож пара тримається на спільному
 * індексі рядка. Розходження тут означає вимкнену не ту систему — тобто інший
 * засіб платежу в рядку 19 фіскального чека.
 */
class AdvanceSourcesFormTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $saved = [];

    public function testNamesPairWithTheirOwnSwitches(): void
    {
        $config = $this->save([
            'sviat__checkbox__source_enabled' => ['cash' => '1', 'bank_account' => '1'],
            'sviat__checkbox__source_name'    => ['integrator' => [0 => 'NovaPay', 1 => 'LiqPay', 2 => 'WayForPay']],
            // LiqPay вимкнено: його індексу в прапорцях немає
            'sviat__checkbox__source_on'      => ['integrator' => [0 => '1', 2 => '1']],
        ]);

        self::assertSame(['NovaPay', 'LiqPay', 'WayForPay'], $config['names']['integrator'], 'усі назви лишаються');
        self::assertSame(
            ['cash', 'bank_account', 'integrator:NovaPay', 'integrator:WayForPay'],
            $config['enabled']
        );
    }

    /** Порожній рядок — вільне місце під наступну систему, а не запис. */
    public function testEmptyNameIsIgnored(): void
    {
        $config = $this->save([
            'sviat__checkbox__source_enabled' => ['cash' => '1'],
            'sviat__checkbox__source_name'    => ['integrator' => [0 => 'NovaPay', 1 => '', 2 => '   ']],
            'sviat__checkbox__source_on'      => ['integrator' => [0 => '1', 1 => '1', 2 => '1']],
        ]);

        self::assertSame(['NovaPay'], $config['names']['integrator']);
        self::assertSame(['cash', 'integrator:NovaPay'], $config['enabled']);
    }

    /** Жодного прапорця — набір порожній, тож лишається стандартний. */
    public function testNothingTickedFallsBackToDefaults(): void
    {
        $config = $this->save([]);

        self::assertSame(
            ['cash', 'bank_account', 'internet_banking'],
            $config['enabled']
        );
    }

    /**
     * @param array<string, mixed> $post
     * @return array{enabled: string[], names: array<string, string[]>}
     */
    private function save(array $post): array
    {
        $this->saved = [];

        // Справжній Request, а не заглушка: перевіряється саме те, як він
        // читає $_POST. Заглушка узгодилась би з будь-якою помилкою виклику.
        $_POST = $post;
        $request = new Request();

        $settings = $this->createStub(Settings::class);
        $settings->method('set')->willReturnCallback(
            function ($name, $value) {
                $this->saved[$name] = $value;
            }
        );

        $controller = (new ReflectionClass(CheckboxAdmin::class))->newInstanceWithoutConstructor();

        Closure::bind(
            function () use ($request, $settings) {
                $this->request = $request;
                $this->settings = $settings;
                $this->saveAdvanceSources();
            },
            $controller,
            CheckboxAdmin::class
        )();

        return json_decode($this->saved['sviat__checkbox__advance_sources'], true);
    }
}
