<?php

declare(strict_types=1);

namespace Modules\Contacts\Domain\Enums;

enum AgreementStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return (string) __('contacts::contacts.enums.agreement_status.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Active => 'success',
            self::Paused => 'warning',
            self::Ended => 'gray',
        };
    }

    /**
     * Whether the agreement still needs its review date watched: an ended one never does.
     */
    public function isOpen(): bool
    {
        return $this !== self::Ended;
    }

    /**
     * @return list<string> the values that are still open
     */
    public static function openValues(): array
    {
        return [self::Active->value, self::Paused->value];
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
