<?php

namespace webhubworks\verifiedentries\services\singletons;

use Craft;
use craft\helpers\DateTimeHelper;
use webhubworks\verifiedentries\enums\VerificationPeriod;
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
}
