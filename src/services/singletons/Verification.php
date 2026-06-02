<?php

namespace webhubworks\verifiedentries\services\singletons;

use Craft;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use DateTime;
use DateTimeZone;
use webhubworks\verifiedentries\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedentries\enums\VerificationPeriod;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Component;

/**
 * The Verification service represents logic related to verifiable entries and their verification status.
 *
 * @property-read array $periodOptionsWithCustomDate
 * @property-read string $addOptionFn
 * @property-read array $periodOptions
 */
class Verification extends Component
{
    /**
     * Returns if the entry has been saved yet.
     *
     * @param int|null $entryId
     * @param int $siteId
     * @return bool
     */
    public function isFirstSave(?int $entryId, int $siteId): bool
    {
        if (! $entryId) {
            return true;
        }

        if (! $this->hasVerificationRow($entryId, $siteId)) {
            return true;
        }

        return false;
    }

    /**
     * Returns the options for the "Verified until" select field located in the sidebar of an
     * entry's edit page.
     *
     * @param DateTime|null $currentUntilDate The field's currently selected value
     * @param int|null $sectionId
     * @param int|null $siteId
     * @return array The dropdown field's options
     * @see VerificationPeriod enum
     */
    public function getDateOptionsForEntry(
        ?DateTime $currentUntilDate = null,
        ?int      $sectionId = null,
        ?int      $siteId = null,
    ): array
    {
        $formatter = new Formatter();

        $defaultPeriod = null;
        if ($sectionId !== null && $siteId !== null) {
            $sectionDefaults = VerifiedEntries::getInstance()
                ->getSectionSettings()
                ->getDefaultSettingsForSection($sectionId, $siteId);

            $defaultPeriod = $sectionDefaults?->period;
        }

        $options = [];

        if ($currentUntilDate) {
            $options[] = [
                'label' => $formatter->asDate($currentUntilDate),
                'value' => $currentUntilDate->format('Y-m-d'),
            ];
        }

        foreach (VerificationPeriod::intervals() as $period) {
            $dateInterval = $period->toDateInterval();

            $date = DateTimeHelper::now()->add($dateInterval);

            if ($currentUntilDate && $date->format('Y-m-d') === $currentUntilDate->format('Y-m-d')) {
                continue;
            }

            $options[] = [
                'label' => $formatter->asDate($date),
                'value' => $date->format('Y-m-d'),
                'data' => [
                    'hint' => implode(' ', [
                        DateTimeHelper::humanDuration($dateInterval),
                        $period->value === $defaultPeriod ? Craft::t(VerifiedEntries::HANDLE, '(Standard)') : ''
                    ])
                ],
            ];
        }

        $options[] = [
            'label' => Craft::t(VerifiedEntries::HANDLE, 'Indefinitely'),
            'value' => false,
        ];

        return $options;
    }

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
     * Returns JavaScript code to manage Craft's custom-date modal for selecting a specific
     * verification date.
     *
     * @return string JS code to be executed by Craft
     * @noinspection JSUnresolvedReference
     */
    public function getAddOptionFn(): string
    {
        return <<<JS
            (createOption, selectize) => {
                const modal = new Craft.CpModal('verified-entries/custom-date');
        
                modal.on('submit', ({response}) => {
                    const {date, label} = response.data;
        
                    createOption({
                        text: label,
                        value: date,
                    });
        
                    setTimeout(() => {
                        selectize.setValue(date);
                    }, 10);
                });
        
                modal.on('close', () => {
                    if (selectize.lastValidValue === '__add__') {
                        selectize.lastValidValue = '';
                    }
                    selectize.focus();
                    Garnish.uiLayerManager.removeLayerAtIndex(1)
                });
            }
        JS;
    }

    /**
     * Returns URL query params for an entry's edit page that corresponds to set values in the
     * plugin's verification fields.
     *
     * @param int|null $reviewerId
     * @return string The URL query params
     */
    public function getFilterParams(?int $reviewerId = null): string
    {
        $condition = new EntryCondition(Entry::class);

        $verifiedRule = new VerifiedConditionRule();
        $verifiedRule->value = false;
        $condition->addConditionRule($verifiedRule);

        if ($reviewerId !== null) {
            $reviewerRule = new ReviewerConditionRule();
            $reviewerRule->setElementIds([$reviewerId]);
            $condition->addConditionRule($reviewerRule);
        }

        $config = [
            'condition' => $condition->getConfig()
        ];

        return UrlHelper::buildQuery($config);
    }

    /**
     * Format a "Verified until" dropdown date to a human-readable value for convenience to the user.
     *
     * @param DateTime|null $verifiedUntilDate
     * @return string
     * @throws \DateInvalidTimeZoneException
     * @throws \DateMalformedStringException
     */
    public function makeVerificationDateReadable(?DateTime $verifiedUntilDate): string
    {
        if ($verifiedUntilDate === null) {
            return Craft::t(VerifiedEntries::HANDLE, 'Indefinitely');
        }

        $timezone = new DateTimeZone(Craft::$app->getTimeZone());
        $now = new DateTime('today', $timezone);
        $dateOnly = new DateTime($verifiedUntilDate->format('Y-m-d'), $timezone);
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
