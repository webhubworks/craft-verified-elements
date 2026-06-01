<?php

namespace webhubworks\verifiedentries\enums;

use Craft;
use craft\enums\Color;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Enum representing the verification status of an entry assigned to a Reviewer.
 */
enum VerificationStatus: string
{
    case Expired = 'expired';
    case Indefinite = 'indefinite';
    case Unassigned = 'unassigned';
    case Verified = 'verified';

    public function handle(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return Craft::t(VerifiedEntries::HANDLE, match ($this) {
            self::Expired => 'Expired',
            self::Indefinite => 'Indefinite',
            self::Unassigned => 'Unassigned',
            self::Verified => 'Verified',
        });
    }

    public function color(): Color
    {
        return match ($this) {
            self::Expired => Color::Red,
            self::Indefinite => Color::Gray,
            self::Unassigned => Color::Yellow,
            self::Verified => Color::Teal,
        };
    }

    public function cssColor(): string
    {
        return 'var(--' . $this->color()->value . '-500)';
    }
}
