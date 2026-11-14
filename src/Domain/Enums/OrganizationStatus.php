<?php

declare(strict_types=1);

namespace Modules\Contacts\Domain\Enums;

enum OrganizationStatus: string
{
    case Active = 'active';
    case Archived = 'archived';

    public function label(): string
    {
        return (string) __('contacts::contacts.enums.status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Archived => 'gray',
        };
    }

    /**
     * @return array<string, string> value => label, for selects and filters
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $status) {
            $options[$status->value] = $status->label();
        }

        return $options;
    }
}
