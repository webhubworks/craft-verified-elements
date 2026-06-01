<?php

namespace webhubworks\verifiedentries\enums;

use DateInterval;

/**
 * Enum representing the options an admin can choose in the CP for when an entry needs verification (e.g. the
 * "Verified until" dropdown field).
 */
enum VerificationPeriod: string
{
    case SevenDays = 'P7D';
    case ThirtyDays = 'P30D';
    case NinetyDays = 'P90D';
    case OneYear = 'P1Y';
    case SpecificDate = 'specific-date';
    case Indefinitely = 'indefinitely';

    /** @return self[] */
    public static function intervals(): array
    {
        return [self::SevenDays, self::ThirtyDays, self::NinetyDays, self::OneYear];
    }

    public function toDateInterval(): DateInterval
    {
        // TODO handle date exception
        return new DateInterval($this->value);
    }
}
