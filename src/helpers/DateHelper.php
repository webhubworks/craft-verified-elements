<?php

namespace webhubworks\verifiedentries\helpers;

use Carbon\Carbon;
use Craft;
use craft\helpers\DateTimeHelper;
use DateInterval;
use DateTime;
use DateTimeZone;
use Exception;
use webhubworks\verifiedentries\enums\DateStatus;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Helper methods for handling date/time.
 */
class DateHelper
{
    /**
     * The number of days that a verified entry is considered "imminent" for expiration. If the
     * entry's "Verified until" date is less than 30 days away, the entry is considered "imminent".
     */
    public const int IMMINENT_WINDOW_DAYS = 30;

    /**
     * Returns the current DateTime object (via Carbon) set to the system's timezone.
     *
     * @param DateTimeZone|string|null $timeZone
     * @return Carbon
     */
    public static function now(DateTimeZone|null|string $timeZone = null): Carbon
    {
        return Carbon::now($timeZone ?? Craft::$app->getTimeZone());
    }

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
            return DateStatus::Indefinite->label();
        }

        $systemTimeZone = self::createDateTimeZone();
        $now = self::now($systemTimeZone);
        $dateOnly = Carbon::createFromFormat(
            'Y-m-d',
            $date->format('Y-m-d'),
            $systemTimeZone
        );
        $diff = $now->diff($dateOnly);

        if ($diff->days === 0) {
            return Craft::t('app', 'Today');
        }

        if ($diff->days < 31) {
            $message = $diff->invert ? '{n} days ago' : '{n} days remaining';

            return Craft::t(VerifiedEntries::HANDLE, $message, ['n' => $diff->days]);
        }

        return Craft::$app->getFormatter()->asDate($date, 'short');
    }

    /**
     * Detect if a value is a date string like "2026-05-24" or datetime string like
     * "2026-05-24 12:00:00", for example.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isDateString(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}/', $value);
    }

    /**
     * Detect if a value is a date string like "2026-05-24" (without time added), for example.
     *
     * @param mixed $value
     * @return bool
     */
    public static function isDateOnlyString(mixed $value): bool
    {
        if (! is_string($value)) {
            return false;
        }

        return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    /**
     * Returns the upper bound of the "imminent" verification window: an entry is "imminent" when
     * its expiry falls between now and this date. A rolling 30-day lookahead from now.
     *
     * @return Carbon
     */
    public static function imminentDateMax(): Carbon
    {
        return self::now()->addDays(self::IMMINENT_WINDOW_DAYS);
    }
}
