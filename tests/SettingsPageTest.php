<?php

namespace Modules\Sviat\Checkbox;

use PHPUnit\Framework\TestCase;

/**
 * Контролер перезаписує налаштування цілком, тож поле, яке зникло зі сторінки,
 * мовчки стирає значення. Головне питання сторінки: коли буде чек.
 */
class SettingsPageTest extends TestCase
{
    private const string MODULE = __DIR__ . '/../../../../Okay/Modules/Sviat/Checkbox';
    private const array FIELDS = [
            'session_id',
            'sviat__checkbox__cashier_license_key',
            'sviat__checkbox__cashier_login',
            'sviat__checkbox__cashier_password',
            'sviat__checkbox__chain_format',
            'sviat__checkbox__chain_prefix',
            'sviat__checkbox__create_receipt_on_received',
            'sviat__checkbox__installed_at',
            'sviat__checkbox__order_status_id',
            'sviat__checkbox__receipt_text',
            'sviat__checkbox__require_payment_method',
            'sviat__checkbox__send_message',
            'sviat__checkbox__settings_form',
            'sviat__checkbox__source_enabled[',
            'sviat__checkbox__source_name[',
            'sviat__checkbox__source_on[',
    ];
    private const array TABS = ['checkbox_cashier', 'checkbox_triggers', 'checkbox_receipt', 'checkbox_advance', 'checkbox_help'];
    private const array KEYS = [
        'state_not_configured', 'state_not_configured_hint', 'state_shift_open', 'state_shift_open_hint',
        'state_shift_closed', 'state_shift_closed_hint',
        'tab_cashier', 'tab_triggers', 'tab_receipt', 'tab_advance', 'tab_help',
        'triggers_intro', 'trigger_paid_title', 'trigger_paid_text', 'trigger_always',
        'trigger_np_title', 'trigger_np_text', 'trigger_module_required', 'trigger_status_title', 'order_status_hint',
        'no_receipt_title', 'no_receipt_skip', 'no_receipt_exists', 'no_receipt_empty', 'no_receipt_advance',
        'additional_title',
    ];

    private function template(): string
    {
        return (string) file_get_contents(self::MODULE . '/Backend/design/html/checkbox_admin.tpl');
    }

    public function testEveryFieldStaysOnThePage(): void
    {
        foreach (self::FIELDS as $field) {
            self::assertStringContainsString('name="' . $field, $this->template());
        }
    }

    public function testEachTaskHasItsOwnTab(): void
    {
        foreach (self::TABS as $tab) {
            self::assertStringContainsString('<a href="#' . $tab . '" class="heading_box tab_navigation_link">', $this->template());
            self::assertStringContainsString('<div id="' . $tab . '" class="tab">', $this->template());
        }
    }

    public function testEveryReceiptTriggerLivesOnOneTab(): void
    {
        $template = $this->template();
        $tab = substr($template, (int) strpos($template, 'id="checkbox_triggers"'));
        $tab = substr($tab, 0, (int) strpos($tab, 'id="checkbox_receipt"'));

        foreach (['sviat__checkbox__create_receipt_on_received', 'sviat__checkbox__order_status_id', 'sviat__checkbox__installed_at', 'no_receipt_title'] as $needle) {
            self::assertStringContainsString($needle, $tab);
        }
    }

    /** Без модуля Нової Пошти велике повідомлення ламало рядок і перекривало опис. */
    public function testTriggerControlsHoldNoAlerts(): void
    {
        preg_match_all('#sviat__checkbox_trigger__control">(.*?)</div>\s*</div>\s*\n#s', $this->template(), $controls);

        self::assertNotEmpty($controls[1]);
        self::assertStringNotContainsString('alert', implode('', $controls[1]));
        self::assertStringContainsString('sviat__checkbox__trigger_module_required|escape', $this->template());
    }

    public function testSaveStaysVisibleOnEveryTab(): void
    {
        $template = $this->template();

        $save = strpos($template, '<button type="submit" class="btn btn_blue">');

        self::assertGreaterThan(strrpos($template, 'class="tab"'), $save);
        self::assertLessThan(strpos($template, '</form>'), $save, 'кнопка збереження — у формі налаштувань');
    }

    public function testShiftStateComesFirst(): void
    {
        $template = $this->template();

        self::assertLessThan(strpos($template, '<form'), strpos($template, 'sviat__checkbox_state'));
    }

    public function testTextsExistInEveryLanguage(): void
    {
        foreach (['ua', 'en', 'ru'] as $language) {
            $lang = [];
            require self::MODULE . "/Backend/lang/$language.php";

            foreach (self::KEYS as $key) {
                self::assertNotEmpty($lang['sviat__checkbox__' . $key] ?? null, "$language: $key");
            }
        }
    }

    /** На стоку немає токенів --ok-*, без запасного значення колір зникне. */
    public function testNewStylesWorkWithoutForkTokens(): void
    {
        $css = (string) file_get_contents(self::MODULE . '/Backend/design/css/checkbox.css');
        $block = substr($css, (int) strpos($css, '.sviat__checkbox_state'));

        self::assertNotSame('', $block);
        self::assertSame(0, preg_match('/var\(--[\w-]+\)/', $block));
    }
}
