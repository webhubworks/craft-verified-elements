<?php

namespace webhubworks\verifiedentries\services\singletons;

use Carbon\Carbon;
use Craft;
use craft\helpers\DateTimeHelper;
use DateTime;
use DateTimeZone;
use webhubworks\verifiedentries\enums\VerificationPeriod;
use webhubworks\verifiedentries\helpers\DateHelper;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Component;

/**
 * The Verification service represents logic related to verifiable entries and their verification status.
 *
 * @property-read array $periodOptionsWithCustomDate
 * @property-read array $periodOptions
 */
class Verification extends Component
{
    /**
     * Returns verification period intervals as options for a select field.
     *
     * An example of this can be found in the plugin's settings page in the "Default Period"
     * column.
     *
     * @return array The default options
     * @see VerificationPeriod enum
     */
    public function getPeriodOptions(): array
    {
        $options = [];

        foreach (VerificationPeriod::intervals() as $period) {
            $dateInterval = $period->toDateInterval();

            $options[] = [
                'label' => DateTimeHelper::humanDuration($dateInterval),
                'value' => $period->value,
            ];
        }

        $options[] = [
            'label' => Craft::t(VerifiedEntries::HANDLE, 'Indefinitely'),
            'value' => VerificationPeriod::Indefinitely->value,
        ];

        return $options;
    }

    /**
     * Returns verification period intervals as options for a select field with the additional
     * option to choose an arbitrary date.
     *
     * An example of this can be found in an entry's sidebar when selecting a custom
     * "Verified until" date.
     *
     * @return array The dropdown's options
     * @see VerificationPeriod enum
     */
    public function getPeriodOptionsWithCustomDate(): array
    {
        $options = $this->getPeriodOptions();

        $options[] = [
            'label' => Craft::t(VerifiedEntries::HANDLE, 'Specific Date'),
            'value' => VerificationPeriod::SpecificDate->value,
        ];

        return $options;
    }

    /**
     * Format a "Verified until" dropdown date to a human-readable value for convenience to the user.
     *
     * @param DateTime|null $verifiedUntilDate
     * @return string
     */
    public function makeVerificationDateReadable(?DateTime $verifiedUntilDate): string
    {
        if ($verifiedUntilDate === null) {
            return Craft::t(VerifiedEntries::HANDLE, 'Indefinitely');
        }

        $systemTimeZone = DateHelper::createDateTimeZone();
        $now = Carbon::now($systemTimeZone);
        $dateOnly = Carbon::createFromFormat(
            'Y-m-d',
            $verifiedUntilDate->format('Y-m-d'),
            $systemTimeZone)
        ;
        $diff = $now->diff($dateOnly);

        if ($diff->days === 0) {
            return Craft::t(VerifiedEntries::HANDLE, 'Today');
        }

        if ($diff->days < 31) {
            return $diff->invert
                ? Craft::t(VerifiedEntries::HANDLE, '{n} days ago', ['n' => $diff->days])
                : Craft::t(VerifiedEntries::HANDLE, '{n} days remaining', ['n' => $diff->days]);
        }

        return Craft::$app->getFormatter()->asDate($verifiedUntilDate, 'short');
    }
}
