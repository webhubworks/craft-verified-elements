<?php

namespace webhubworks\verifiedelements\enums;

use Craft;
use craft\enums\Color;
use webhubworks\verifiedelements\Plugin;

/**
 * Enum representing whether an entry has a concrete "Verified until" date or if this value is
 * set to "Indefinitely".
 */
enum DateStatus: string
{
    case HasDate = 'hasDate';
    case Indefinite = 'indefinite';

    public function handle(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return Craft::t(Plugin::HANDLE, match ($this) {
            self::HasDate => 'Has Date',
            self::Indefinite => 'Indefinite',
        });
    }

    public function color(): Color
    {
        return match ($this) {
            self::HasDate => Color::Teal,
            self::Indefinite => Color::Gray,
        };
    }

    public function cssColor(): string
    {
        return 'var(--' . $this->color()->value . '-500)';
    }
}
