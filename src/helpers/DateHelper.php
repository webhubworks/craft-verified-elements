<?php

namespace webhubworks\verifiedentries\helpers;

use craft\helpers\DateTimeHelper;
use DateInterval;
use DateTime;
use Exception;

/**
 * Helper methods for handling date/time.
 */
class DateHelper
{
    /**
     * Wraps Craft's `toDateTime`, converting false returns and any internal exceptions to null.
     *
     * @param mixed $value
     * @return DateTime|null
     */
    public static function toDateTime(mixed $value): ?DateTime
    {
        try {
            $result = DateTimeHelper::toDateTime($value);
            return $result ?: null;
        }
        catch (Exception $exception) {
            Log::error('Failed to parse datetime value', $exception);
            return null;
        }
    }

    /**
     * Wraps `new DateInterval()`, converting exceptions from malformed period strings to null.
     *
     * @param string $period An ISO 8601 duration string (e.g. "P30D", "P1Y").
     * @return DateInterval|null
     */
    public static function createDateInterval(string $period): ?DateInterval
    {
        try {
            return new DateInterval($period);
        }
        catch (Exception $exception) {
            Log::error('Failed to create DateInterval from period string', $exception);
            return null;
        }
    }
}
