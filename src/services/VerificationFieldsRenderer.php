<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Cp;
use craft\helpers\DateTimeHelper;
use craft\i18n\Formatter;
use DateTime;
use Throwable;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\enums\Permission;
use webhubworks\verifiedentries\enums\VerificationPeriod;
use webhubworks\verifiedentries\helpers\Log;
use webhubworks\verifiedentries\services\singletons\PluginSettings;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Construct the HTML for the "Reviewer" and "Verified until" date fields that appear in the
 * sidebar of an entry's "edit" page in the CP.
 */
readonly class VerificationFieldsRenderer
{
    /** @var Entry|VerifiableBehavior $entry */

    public function __construct(
        private Entry           $entry,
        private bool            $canVerifyEntries,
        private PluginSettings $settings
    ) {}

    /**
     * @return string
     */
    public function buildReviewerFieldHtml(): string
    {
        $reviewer = $this->entry->getReviewer();

        $config = [
            'id' => 'reviewerId',
            'name' => 'reviewerId',
            'label' => Craft::t(VerifiedEntries::HANDLE, 'Reviewer'),
            'single' => true,
            'elementType' => User::class,
            'elements' => $reviewer ? [$reviewer] : null,
            'criteria' => [
                'status' => 'active',
                'can' => Permission::VerifyEntries->value,
            ],
            'disabled' => ! $this->canVerifyEntries,
        ];

        try {
            return Cp::elementSelectHtml($config);
        }
        catch (Throwable $exception) {
            Log::error('Error rendering "Reviewer" field', $exception);
            return '';
        }
    }

    /**
     * @return string
     */
    public function buildVerifiedUntilDateFieldHtml(): string
    {
        $dropdownFieldOptions = self::getDateOptionsForEntry(
            $this->settings,
            $this->entry->getVerifiedUntilDate(),
            $this->entry->sectionId,
            $this->entry->siteId
        );

        $config = [
            'id' => 'verifiedUntilDate',
            'name' => 'verifiedUntilDate',
            'options' => $dropdownFieldOptions,
            'selectizeOptions' => [
                'allowEmptyOption' => false,
                'autocomplete' => false,
            ],
            'value' => $this->getVerifiedUntilDateValue(),
            'addOptionLabel' => 'specificDate',
            'addOptionFn' => self::addOptionJsFunction(),
            'disabled' => ! $this->canVerifyEntries,
        ];

        try {
            return Cp::selectizeFieldHtml($config);
        }
        catch (Throwable $exception) {
            Log::error('Error rendering "Verified until" date field', $exception);
            return '';
        }
    }


    // PUBLIC STATIC HELPERS
    // =============================================================================================

    /**
     * Returns JavaScript code to manage Craft's custom-date modal for selecting a specific
     * verification date.
     *
     * @return string JS code to be executed by Craft
     * @noinspection JSUnresolvedReference
     */
    public static function addOptionJsFunction(): string
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
     * Returns the options for the "Verified until" select field located in the sidebar of an
     * entry's edit page.
     *
     * @param DateTime|null $currentUntilDate The field's currently selected value
     * @return array The dropdown field's options
     * @see VerificationPeriod
     */
    public static function getDateOptionsForEntry(
        PluginSettings $settings,
        ?DateTime      $currentUntilDate = null,
        ?int           $sectionId = null,
        ?int           $siteId = null
    ): array
    {
        $formatter = new Formatter();

        $defaultPeriod = null;
        if ($sectionId !== null && $siteId !== null) {
            $sectionDefaults = $settings->getDefaultSettingsForSection($sectionId, $siteId);

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
     * @see VerificationPeriod
     */
    public static function periodOptions(): array
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
     * @see VerificationPeriod
     */
    public static function periodOptionsWithCustomDate(): array
    {
        $options = self::periodOptions();

        $options[] = [
            'label' => Craft::t(VerifiedEntries::HANDLE, 'Specific Date'),
            'value' => VerificationPeriod::SpecificDate->value,
        ];

        return $options;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * @return false|string
     */
    private function getVerifiedUntilDateValue(): false|string
    {
        if (! $this->entry->getVerifiedUntilDate()) {
            return false;
        }

        return $this->entry->getVerifiedUntilDate()->format('Y-m-d');
    }
}
