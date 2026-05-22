<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\db\Query;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use DateTime;
use webhubworks\verifiedentries\db\Queries;
use webhubworks\verifiedentries\db\Table;
use webhubworks\verifiedentries\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedentries\enums\VerificationPeriod;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Component;
use yii\db\Exception;

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
     * Adds or updates an entry's verification details ("Verified until" date and "Reviewer" user)
     * in the database.
     *
     * @param int $entryId
     * @param int $siteId
     * @param int|null $reviewerId A Craft User ID
     * @param DateTime|null $verifiedUntilDate
     * @throws Exception
     */
    public function upsertEntryDetails(int $entryId, int $siteId, ?int $reviewerId, ?DateTime $verifiedUntilDate): void
    {
        Db::upsert(Table::ENTRIES, [
            'entryId' => $entryId,
            'siteId' => $siteId,
            'reviewerId' => $reviewerId,
            'verifiedUntilDate' => Db::prepareDateForDb($verifiedUntilDate),
        ], [
            'reviewerId' => $reviewerId,
            'verifiedUntilDate' => Db::prepareDateForDb($verifiedUntilDate),
        ]);
    }

    /**
     * Returns true if a verification row already exists for the given entry and site.
     *
     * @param int $entryId
     * @param int $siteId
     * @return bool
     */
    public function hasVerificationRow(int $entryId, int $siteId): bool
    {
        return Queries::verifiableEntry($entryId, $siteId)->exists();
    }

    /**
     * Seeds a verification row for a propagated site by copying values from an
     * existing row for the same entry. Does nothing if no source row can be found.
     *
     * @param int $entryId
     * @param int $siteId
     * @return void
     * @throws Exception
     */
    public function seedVerificationRow(int $entryId, int $siteId): void
    {
        $sourceRow = (new Query())
            ->select(['reviewerId', 'verifiedUntilDate'])
            ->from(Table::ENTRIES)
            ->where(['entryId' => $entryId])
            ->one();

        if (! $sourceRow) {
            return;
        }

        $verifiedUntilDate = null;
        if (isset($sourceRow['verifiedUntilDate'])) {
            // TODO handle toDateTime exception
            $verifiedUntilDate = DateTimeHelper::toDateTime($sourceRow['verifiedUntilDate']);
        }

        $this->upsertEntryDetails(
            $entryId,
            $siteId,
            $sourceRow['reviewerId'],
            $verifiedUntilDate
        );
    }

    /**
     * Returns the options for the "Verified until" select field located in the sidebar of an
     * entry's edit page.
     *
     * @param DateTime|null $currentUntilDate The field's currently selected value
     * @param int|null $sectionId
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
            $defaults = VerifiedEntries::getInstance()
                ->getSectionSettings()
                ->getDefaultSettingsForSection($sectionId, $siteId);

            [$reviewerId, $defaultPeriod] = $defaults ?? [null, null];
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
     * Runs the action of checking for entries whose verification date is in the past
     * and notifying the entry's Reviewer user via email.
     *
     * @return array The expired verification entries
     * @see self::notifyAboutExpiredEntries()
     */
    public function checkExpiredVerifications(): array
    {
        // Find entries where verification date is in the past
        $expiredEntries = Queries::expiredVerifiableEntries()->all();

        if (! empty($expiredEntries)) {
            // Log the expired entries
            Craft::warning(
                Craft::t(
                    VerifiedEntries::HANDLE,
                    'Found {count} entries with expired verification dates',
                    ['count' => count($expiredEntries)]
                ),
                __METHOD__
            );

            $this->notifyAboutExpiredEntries($expiredEntries);
        }

        return $expiredEntries;
    }

    /**
     * Runs the action of notifying Reviewers about expired verification entries.
     *
     * @param array $expiredEntries
     * @return void
     */
    public function notifyAboutExpiredEntries(array $expiredEntries): void
    {
        $entriesPerReviewer = collect($expiredEntries)
            ->groupBy('reviewerId');

        foreach ($entriesPerReviewer as $reviewerId => $entries) {
            /** @var User $reviewer */
            $reviewer = User::find()
                ->id($reviewerId)
                ->status('active')
                ->one();

            if (! $reviewer) {
                Craft::warning(
                    "Could not notify reviewer ($reviewerId) about expired entries",
                    __METHOD__
                );
                continue;
            }

            VerifiedEntries::getInstance()->getNotifications()->sendExpiredNotification($reviewer, $entries);
        }
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
}
