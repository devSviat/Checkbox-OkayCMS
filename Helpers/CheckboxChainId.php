<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

/**
 * Ідентифікатор ланцюжка передплати.
 *
 * Це не внутрішнє значення: Checkbox друкує його в чеку авансу — синтетична
 * позиція називається «Передплата за замовленням #<relation_id>». Тому формат
 * бачить і менеджер, і клієнт.
 *
 * Схема Checkbox (`PrePaymentReceiptPayload.custom_relation_id`, версія API
 * 2.106.4) задає межі: рядок від 10 до 256 символів. Тому голий номер
 * замовлення передати не можна — «22674» це п'ять символів, API відхилить.
 * Не передавати теж не варіант: поле необов'язкове, але тоді Checkbox
 * підставляє власний номер на кшталт «1717502471», і в чеку опиняється число,
 * яке ні з чим не звірити.
 *
 * Звідси два способи дотягнути до десяти: префікс перед номером або нулі
 * зліва. Що саме — обирає магазин у налаштуваннях.
 *
 * Унікальність у межах організації дає безкоштовний захист від подвійного
 * натискання: повторний виклик із тим самим значенням Checkbox відхиляє
 * замість створення другого ланцюжка. Доповнення нулями цього не псує —
 * номери замовлень цілі, тож провідні нулі ні з чим не збігаються.
 */
final class CheckboxChainId
{
    /** Межі поля custom_relation_id у схемі Checkbox. */
    const MIN_LENGTH = 10;
    const MAX_LENGTH = 256;

    const FORMAT_PREFIX = 'prefix';
    const FORMAT_DIGITS = 'digits';

    const DEFAULT_PREFIX = 'prepayment-';

    /** Довший префікс не лишає місця під номер і нічого не додає по суті. */
    const PREFIX_MAX_LENGTH = 32;

    /**
     * @param int $existingChains скільки ланцюжків це замовлення вже мало
     *                            (повернутий ланцюжок не звільняє свій ідентифікатор)
     * @param string|null $prefix null — формат «лише цифри»
     */
    public static function build(int $orderId, int $existingChains = 0, ?string $prefix = null): string
    {
        $prefix = $prefix === null ? '' : $prefix;
        $suffix = $existingChains > 0 ? '-' . ($existingChains + 1) : '';

        // Номер доповнюється нулями рівно настільки, щоб дотягнути до мінімуму
        // разом із префіксом. Суфікс повторного ланцюжка в розрахунок не йде:
        // перший ланцюжок замовлення його не має, а довжина мусить вистачати
        // саме йому.
        $room = self::MIN_LENGTH - mb_strlen($prefix);
        $number = str_pad((string)$orderId, max(1, $room), '0', STR_PAD_LEFT);

        return $prefix . $number . $suffix;
    }

    /**
     * Префікс, як його налаштував магазин, або null для формату «лише цифри».
     *
     * @param mixed $format
     * @param mixed $prefix
     */
    public static function configuredPrefix($format, $prefix): ?string
    {
        if ($format === self::FORMAT_DIGITS) {
            return null;
        }

        return self::normalisePrefix($prefix);
    }

    /**
     * @param mixed $raw
     * @return string завжди непорожній: порожній префікс дав би формат «лише
     *                цифри» в обхід явного вибору
     */
    public static function normalisePrefix($raw): string
    {
        if (!is_string($raw)) {
            return self::DEFAULT_PREFIX;
        }

        // Керівні байти й кутові дужки — не для рядка, який друкується в чеку
        // й їде в шлях запиту.
        $prefix = trim((string)preg_replace('~[\x00-\x1F\x7F<>]~', '', $raw));
        if ($prefix === '') {
            return self::DEFAULT_PREFIX;
        }

        return mb_substr($prefix, 0, self::PREFIX_MAX_LENGTH);
    }
}
