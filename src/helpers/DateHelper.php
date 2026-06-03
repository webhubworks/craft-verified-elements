<?php

namespace webhubworks\verifiedentries\helpers;

use Carbon\Carbon;
use Craft;
use craft\helpers\DateTimeHelper;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use webhubworks\verifiedentries\VerifiedEntries;

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
        }
        catch (Exception $exception) {
            Log::error('Failed to parse datetime value', $exception);
            return null;
        }

        if ($result === false && $value !== null) {
            Log::warning(sprintf('Could not parse datetime value: %s', $value));
        }

        return $result ?: null;
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

    /**
     * Format a "Verified until" dropdown date to a human-readable value for convenience to the user.
     *
     * @param DateTime|null $date
     * @return string
     */
    public static function readableVerificationDate(?DateTime $date): string
    {
        if ($date === null) {
            return Craft::t(VerifiedEntries::HANDLE, 'Indefinitely');
        }

        $systemTimeZone = self::createDateTimeZone();
        $now = Carbon::now($systemTimeZone);
        $dateOnly = Carbon::createFromFormat(
            'Y-m-d',
            $date->format('Y-m-d'),
            $systemTimeZone);
        $diff = $now->diff($dateOnly);

        if ($diff->days === 0) {
            return Craft::t('app', 'Today');
        }

        if ($diff->days < 31) {
            return $diff->invert
                ? Craft::t(VerifiedEntries::HANDLE, '{n} days ago', ['n' => $diff->days])
                : Craft::t(VerifiedEntries::HANDLE, '{n} days remaining', ['n' => $diff->days]);
        }

        return Craft::$app->getFormatter()->asDate($date, 'short');
    }
}
