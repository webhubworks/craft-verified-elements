<?php

namespace webhubworks\verifiedentries\helpers;

use Craft;
use craft\helpers\DateTimeHelper;
use DateInterval;
use DateTime;
use DateTimeZone;
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

    /**
     * Returns a new DateTimeZone object and handles errors.
     *
     * @param string|null $timeZone
     * @return DateTimeZone
     */
    public static function createDateTimeZone(?string $timeZone = null): DateTimeZone
    {
        if (! $timeZone) {
            $timeZone = Craft::$app->getTimeZone();
        }

        try {
            return new DateTimeZone($timeZone);
        }
        catch (Exception $exception) {
            Log::error('Failed to create DateTimeZone', $exception);
            return new DateTimeZone('UTC');
        }
    }
}
