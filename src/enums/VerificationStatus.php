<?php

namespace webhubworks\verifiedelements\enums;

use Craft;
use craft\enums\Color;
use webhubworks\verifiedelements\Plugin;

/**
 * Enum representing the verification status of an entry assigned to a Reviewer.
 */
enum VerificationStatus: string
{
    case Verified = 'verified';
    case Expired = 'expired';

    public function handle(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return Craft::t(Plugin::HANDLE, match ($this) {
            self::Verified => 'Verified',
            self::Expired => 'Expired',
        });
    }

    public function color(): Color
    {
        return match ($this) {
            self::Verified => Color::Teal,
            self::Expired => Color::Red,
        };
    }

    public function cssColor(): string
    {
        return 'var(--' . $this->color()->value . '-500)';
    }
}
