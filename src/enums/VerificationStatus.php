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
    case Unverified = 'unverified';
    case Unassigned = 'unassigned';
    case Verified = 'verified';
    case Expired = 'expired';

    public function handle(): string
    {
        return $this->value;
    }

    public function color(): Color
    {
        return match ($this) {
            self::Unverified => Color::Gray,
            self::Unassigned => Color::Orange,
            self::Verified => Color::Teal,
            self::Expired => Color::Red,
        };
    }

    public function label(): string
    {
        return Craft::t(VerifiedEntries::HANDLE, match ($this) {
            self::Unverified => 'Unverified',
            self::Unassigned => 'Unassigned',
            self::Verified => 'Verified',
            self::Expired => 'Expired',
        });
    }
}
