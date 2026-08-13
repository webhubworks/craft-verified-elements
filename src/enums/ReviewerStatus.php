<?php

namespace webhubworks\verifiedelements\enums;

use Craft;
use craft\enums\Color;
use webhubworks\verifiedelements\Plugin;

/**
 * Enum representing whether an entry has a Craft `User` assigned to review the entry when it
 * expires (the "Reviewer").
 */
enum ReviewerStatus: string
{
    case Assigned = 'assigned';
    case Unassigned = 'unassigned';

    public function handle(): string
    {
        return $this->value;
    }

    public function label(): string
    {
        return Craft::t(Plugin::HANDLE, match ($this) {
            self::Assigned => 'Assigned',
            self::Unassigned => 'Unassigned',
        });
    }

    public function color(): Color
    {
        return match ($this) {
            self::Assigned => Color::Blue,
            self::Unassigned => Color::Yellow,
        };
    }

    public function cssColor(): string
    {
        return 'var(--' . $this->color()->value . '-500)';
    }
}
