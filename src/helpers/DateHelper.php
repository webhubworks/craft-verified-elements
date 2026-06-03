<?php

namespace webhubworks\verifiedentries\helpers;

use craft\helpers\DateTimeHelper;
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
}
