<?php

declare(strict_types=1);

namespace Modules\Contacts\Domain\Enums;

enum OrganizationType: string
{
    case Supplier = 'supplier';
    case Shop = 'shop';
    case Association = 'association';
    case Institution = 'institution';
    case Sponsor = 'sponsor';
    case Media = 'media';
    case Other = 'other';

    public function label(): string
    {
        return (string) __('contacts::contacts.enums.type.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Supplier => 'info',
            self::Shop => 'success',
            self::Association => 'primary',
            self::Institution => 'warning',
            self::Sponsor => 'danger',
            self::Media, self::Other => 'gray',
        };
    }

    /**
     * @return array<string, string> value => label, for selects and filters
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $type) {
            $options[$type->value] = $type->label();
        }

        return $options;
    }
}
