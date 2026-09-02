<?php

namespace Modules\Sviat\Checkbox;

use Okay\Modules\Sviat\Checkbox\Helpers\CheckboxChainId;
use PHPUnit\Framework\TestCase;

/**
 * Checkbox вимагає від custom_relation_id унікальності в межах організації і
 * довжини від 10 до 256 символів. Порушення довжини — 422 і невиставлений чек.
 *
 * Значення не внутрішнє: Checkbox друкує його в чеку рядком «Передплата за
 * замовленням #…», тож формат бачить і клієнт.
 */
class ChainIdTest extends TestCase
{
    public function testIdContainsOrderNumber(): void
    {
        self::assertSame('prepayment-1234', CheckboxChainId::build(1234, 0, 'prepayment-'));
    }

    /**
     * Найкоротший можливий випадок — замовлення №1. Саме на ньому короткий
     * префікс не дотягує до межі, і саме його ніхто не перевіряє руками:
     * магазин налаштовує формат, дивлячись на п'ятизначні номери.
     *
     * @dataProvider shortCaseProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('shortCaseProvider')]
    public function testEveryFormatMeetsTheApiMinimum(?string $prefix, int $orderId, string $expected): void
    {
        $id = CheckboxChainId::build($orderId, 0, $prefix);

        self::assertSame($expected, $id);
        self::assertGreaterThanOrEqual(CheckboxChainId::MIN_LENGTH, mb_strlen($id));
        self::assertLessThanOrEqual(CheckboxChainId::MAX_LENGTH, mb_strlen($id));
    }

    /** @return array<string, array{0: string|null, 1: int, 2: string}> */
    public static function shortCaseProvider(): array
    {
        return [
            'лише цифри, №1'          => [null, 1, '0000000001'],
            'лише цифри, №22674'      => [null, 22674, '0000022674'],
            'лише цифри, довгий номер' => [null, 1234567890123, '1234567890123'],
            'короткий префікс, №7'    => ['order-', 7, 'order-0007'],
            'короткий префікс, №22674' => ['order-', 22674, 'order-22674'],
            'типовий префікс, №1'     => ['prepayment-', 1, 'prepayment-1'],
            'довгий префікс, №1'      => [str_repeat('x', 12), 1, str_repeat('x', 12) . '1'],
        ];
    }

    public function testRepeatedChainForSameOrderGetsSuffix(): void
    {
        self::assertSame('prepayment-1234-2', CheckboxChainId::build(1234, 1, 'prepayment-'));
        self::assertSame('prepayment-1234-3', CheckboxChainId::build(1234, 2, 'prepayment-'));
        self::assertSame('0000022674-2', CheckboxChainId::build(22674, 1, null));
    }

    /**
     * Доповнення нулями не має злити два замовлення в один ідентифікатор:
     * повторний виклик із тим самим значенням Checkbox відхиляє, і колізія
     * означала б неможливість провести аванс.
     */
    public function testPaddingKeepsIdsDistinct(): void
    {
        $ids = [];
        foreach ([1, 7, 10, 22674, 100000] as $orderId) {
            foreach ([null, 'order-', 'prepayment-'] as $prefix) {
                $ids[] = CheckboxChainId::build($orderId, 0, $prefix);
            }
        }

        self::assertSame(count($ids), count(array_unique($ids)));
    }

    public function testDigitsFormatIsChosenByFormatKey(): void
    {
        self::assertNull(CheckboxChainId::configuredPrefix(CheckboxChainId::FORMAT_DIGITS, 'order-'));
        self::assertSame('order-', CheckboxChainId::configuredPrefix(CheckboxChainId::FORMAT_PREFIX, 'order-'));
        self::assertSame(CheckboxChainId::DEFAULT_PREFIX, CheckboxChainId::configuredPrefix(null, null));
    }

    /**
     * Префікс їде і в чек, і в шлях запиту. Порожній не приймаємо: він мовчки
     * перетворив би формат на «лише цифри» в обхід явного вибору.
     *
     * @dataProvider prefixProvider
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('prefixProvider')]
    public function testPrefixIsCleaned($raw, string $expected): void
    {
        self::assertSame($expected, CheckboxChainId::normalisePrefix($raw));
    }

    /** @return array<string, array{0: mixed, 1: string}> */
    public static function prefixProvider(): array
    {
        return [
            'звичайний'        => ['order-', 'order-'],
            'пробіли навколо'  => ['  order-  ', 'order-'],
            'порожній'         => ['', CheckboxChainId::DEFAULT_PREFIX],
            'лише пробіли'     => ['   ', CheckboxChainId::DEFAULT_PREFIX],
            'не рядок'         => [null, CheckboxChainId::DEFAULT_PREFIX],
            'кутові дужки'     => ['<b>order-', 'border-'],
            'перенос рядка'    => ["order\n-", 'order-'],
            'нульовий байт'    => ["order\x00-", 'order-'],
            'задовгий'         => [str_repeat('a', 50), str_repeat('a', CheckboxChainId::PREFIX_MAX_LENGTH)],
        ];
    }
}
