<?php

namespace Okay\Modules\Sviat\Checkbox\Helpers;

use Okay\Modules\Sviat\Checkbox\Init\Init;

/** Форма й засіб оплати для чека та «не відправляти до Checkbox»: рядок способу оплати чи рядок перевізника. */
final readonly class CheckboxPaymentProfile
{
    /** @param ?string $type null — поле не заповнене; чек тоді йде готівкою, як до 1.8.0 */
    public function __construct(
        public ?string $type,
        public ?string $label,
        public bool $skip,
    ) {
    }

    /** Поля Checkbox самого способу оплати. */
    public static function fromMethod(object $method): self
    {
        $type = $method->{Init::PAYMENT_TYPE_FIELD} ?? null;
        $label = $method->{Init::PAYMENT_LABEL_FIELD} ?? null;

        return new self(
            is_string($type) ? $type : null,
            is_string($label) && !empty($label) ? $label : null,
            !empty($method->{Init::PAYMENT_SKIP_FIELD}),
        );
    }

    /** @return array{type: string, label: string, skip: bool} */
    public function toArray(): array
    {
        return ['type' => (string)$this->type, 'label' => (string)$this->label, 'skip' => $this->skip];
    }
}
