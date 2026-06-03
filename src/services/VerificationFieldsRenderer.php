<?php

namespace webhubworks\verifiedentries\services;

use Carbon\Carbon;
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
        private Entry          $entry,
        private bool           $canVerifyEntries,
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
        $dateSelectOptions = self::dateSelectOptions(
            $this->entry->sectionId,
            $this->entry->siteId,
            $this->settings,
            $this->entry->getVerifiedUntilDate(),
        );

        $config = [
            'id' => 'verifiedUntilDate',
            'name' => 'verifiedUntilDate',
            'options' => $dateSelectOptions,
            'selectizeOptions' => [
                'allowEmptyOption' => false,
                'autocomplete' => false,
            ],
            'value' => $this->getVerifiedUntilDateValue(),
            'addOptionLabel' => 'specificDate',
            'addOptionFn' => self::jsFunctionToAddCustomDate(),
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
     * Returns "Verified until" date options for a select field.
     *
     * An example of this can be found in an entry's "edit" page. The sidebar has a dropdown field
     * called "Verified until" where you choose a date that indicates when the entry needs to be
     * reviewed.
     *
     * @param int $sectionId
     * @param int $siteId
     * @param PluginSettings $settings
     * @param DateTime|null $currentUntilDate The field's currently selected value
     * @return array The dropdown field's options
     * @see VerificationPeriod
     */
    public static function dateSelectOptions(
        int            $sectionId,
        int            $siteId,
        PluginSettings $settings,
        ?DateTime      $currentUntilDate
    ): array
    {
        $formatter = new Formatter();
        $sectionDefaults = $settings->getDefaultSettingsForSection($sectionId, $siteId);
        $defaultPeriod = $sectionDefaults?->period;

        $options = [];

        // Add the user's selected date at the top of the list of options
        if ($currentUntilDate) {
            $options[] = [
                'label' => $formatter->asDate($currentUntilDate),
                'value' => $currentUntilDate->format('Y-m-d'),
            ];
        }

        foreach (VerificationPeriod::intervals() as $period) {
            $dateInterval = $period->toDateInterval();
            $date = Carbon::now()->add($dateInterval);

            // Don't add the user's selected date to the list
            if ($currentUntilDate && $date->format('Y-m-d') === $currentUntilDate->format('Y-m-d')) {
                continue;
            }

            // Tell the user how many days, months, or years away the date is
            $hint = DateTimeHelper::humanDuration($dateInterval);
            if ($period->value === $defaultPeriod) {
                $hint .= ' (' . Craft::t(VerifiedEntries::HANDLE, 'Standard') . ')';
            }

            $options[] = [
                'label' => $formatter->asDate($date),
                'value' => $date->format('Y-m-d'),
                'data' => ['hint' => $hint],
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
     * An example that includes the custom date is on an entries listing page. If you select
     * entries via their checkboxes and try to apply the "Verify Entry" action
     * (click the gear icon), the dropdown appears in a modal.
     *
     * @return array The default options
     * @see VerificationPeriod
     */
    public static function periodSelectOptions(bool $withCustomDate = false): array
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

        if ($withCustomDate) {
            $options[] = [
                'label' => Craft::t(VerifiedEntries::HANDLE, 'Specific Date'),
                'value' => VerificationPeriod::SpecificDate->value,
            ];
        }

        return $options;
    }

    /**
     * Returns JavaScript code to manage Craft's custom-date modal for selecting a specific
     * verification date.
     *
     * @return string JS code to be executed by Craft
     * @noinspection JSUnresolvedReference
     */
    public static function jsFunctionToAddCustomDate(): string
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
