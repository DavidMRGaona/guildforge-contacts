<?php

declare(strict_types=1);

namespace Modules\Contacts\Domain\Enums;

enum AgreementKind: string
{
    case Discount = 'discount';
    case Space = 'space';
    case Sponsorship = 'sponsorship';
    case Collaboration = 'collaboration';
    case Other = 'other';

    public function label(): string
    {
        return (string) __('contacts::contacts.enums.agreement_kind.'.$this->value);
    }

    /**
     * @return array<string, string> value => label, for selects and filters
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $kind) {
            $options[$kind->value] = $kind->label();
        }

        return $options;
    }
}
