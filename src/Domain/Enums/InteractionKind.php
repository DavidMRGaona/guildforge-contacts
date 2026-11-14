<?php

declare(strict_types=1);

namespace Modules\Contacts\Domain\Enums;

enum InteractionKind: string
{
    case Meeting = 'meeting';
    case Call = 'call';
    case Email = 'email';
    case Message = 'message';
    case Other = 'other';

    public function label(): string
    {
        return (string) __('contacts::contacts.enums.interaction_kind.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Meeting => 'primary',
            self::Call => 'info',
            self::Email => 'success',
            self::Message => 'warning',
            self::Other => 'gray',
        };
    }

    /**
     * @return array<string, string> value => label, for selects
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
