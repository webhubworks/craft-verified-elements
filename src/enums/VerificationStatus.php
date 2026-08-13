<?php

namespace webhubworks\verifiedelements\enums;

use Craft;
use craft\enums\Color;
use DateTime;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\Plugin;

/**
 * Enum representing the verification status of an element assigned to a Reviewer.
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

    public function isVerified(): bool
    {
        return $this !== self::Expired;
    }

    /**
     * Resolves the status for a "Verified until" date.
     *
     * @param DateTime|null $verifiedUntilDate
     * @return self
     */
    public static function fromDate(?DateTime $verifiedUntilDate): self
    {
        // An "Indefinitely" date (null) is considered "verified".
        if ($verifiedUntilDate === null) {
            return self::Verified;
        }

        if ($verifiedUntilDate <= DateHelper::now()) {
            return self::Expired;
        }

        return self::Verified;
    }
}
