<?php

namespace webhubworks\verifiedentries\services\singletons;

use Craft;
use craft\db\Query as CraftQuery;
use craft\db\Table as CraftTable;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use DateInterval;
use DateTime;
use DateTimeZone;
use webhubworks\verifiedentries\base\NotifiableInterface;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\db\PluginQuery;
use webhubworks\verifiedentries\db\PluginTable;
use webhubworks\verifiedentries\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedentries\enums\VerificationPeriod;
use webhubworks\verifiedentries\events\EventRegistrar;
use webhubworks\verifiedentries\mail\ChangeNotification;
use webhubworks\verifiedentries\mail\ExpiredNotification;
use webhubworks\verifiedentries\models\ExpiredEntryData;
use webhubworks\verifiedentries\models\UserRecipient;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Component;
use yii\db\Exception;

/**
 * The Verification service represents logic related to verifiable entries and their verification status.
 *
 * @property-read array $periodOptionsWithCustomDate
 * @property-read string $addOptionFn
 * @property-read ExpiredEntryData[][] $expiredEntriesByReviewer
 * @property-read ExpiredEntryData[] $expiredEntries
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
        Db::upsert(PluginTable::ENTRIES, [
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
        return PluginQuery::verifiableEntry($entryId, $siteId)->exists();
    }

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
        $sourceRow = (new CraftQuery())
            ->select(['reviewerId', 'verifiedUntilDate'])
            ->from(PluginTable::ENTRIES)
            ->where(['entryId' => $entryId])
            ->one();

        if (! $sourceRow) {
            return;
        }

        $verifiedUntilDate = null;
        if (isset($sourceRow['verifiedUntilDate'])) {
            // TODO handle date exception
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
     * Return an array of ExpiredEntryData objects for entries with a verification date in the past.
     *
     * @return ExpiredEntryData[]
     */
    public function getExpiredEntries(): array
    {
        return array_map(
            static fn(array $row) => ExpiredEntryData::fromArray($row),
            PluginQuery::expiredVerifiableEntries()->all()
        );
    }

    /**
     * Return an array of ExpiredEntryData objects for entries with a verification date in the past,
     * but group the entries by their Reviewer user ID.
     *
     * @return array<int, ExpiredEntryData[]>
     */
    public function getExpiredEntriesByReviewer(): array
    {
        $result = [];
        foreach ($this->getExpiredEntries() as $entry) {
            if ($entry->reviewerId === null) {
                continue;
            }

            $result[$entry->reviewerId][] = $entry;
        }

        return $result;
    }

    /**
     * Return an array of ExpiredEntryData objects for entries without a Reviewer assigned to them.
     *
     * @return ExpiredEntryData[]
     */
    public function getUnassignedExpiredEntries(): array
    {
        return array_values(array_filter(
            $this->getExpiredEntries(),
            static fn(ExpiredEntryData $entry) => $entry->reviewerId === null
        ));
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


    // NOTIFICATIONS
    // =============================================================================================

    /**
     * Sends an email to an entry's Reviewer user about entries assigned to them whose verification
     * date is in the past.
     *
     * @param NotifiableInterface $reviewer
     * @param ExpiredEntryData[] $expiredEntries
     * @return bool
     */
    public function sendExpiredNotification(NotifiableInterface $reviewer, array $expiredEntries): bool
    {
        return (new ExpiredNotification($expiredEntries, $reviewer))->send();
    }

    /**
     * Sends a Reviewer an email about a verifiable entry that's assigned to them whenever
     * someone else edits that entry.
     *
     * @param Entry $entry
     * @param NotifiableInterface $reviewer
     * @param string|null $locale
     * @return bool
     */
    public function sendChangeNotification(Entry $entry, NotifiableInterface $reviewer, ?string $locale = null): bool
    {
        return (new ChangeNotification($entry, $reviewer, $locale))->send();
    }


    // EVENTS
    // =============================================================================================

    /**
     * On propagation, only seed the row if one doesn't exist yet. This prevents a save on one
     * site from overwriting verification settings that were independently set on another site.
     *
     * @param int $entryId
     * @param int $siteId
     * @return void
     * @see EventRegistrar::registerEntryLifecycle() // Element::EVENT_AFTER_SAVE
     */
    public function handlePropagationSave(int $entryId, int $siteId): void
    {
        if ($this->hasVerificationRow($entryId, $siteId)) {
            return;
        }

        try {
            $this->seedVerificationRow($entryId, $siteId);
        }
        catch (Exception $exception) {
            $entryTitle = (new CraftQuery())
                ->select(['title'])
                ->from(CraftTable::ELEMENTS_SITES)
                ->where(['elementId' => $entryId, 'siteId' => $siteId])
                ->scalar();

            Craft::error(sprintf(
                'Error seeding verification row for entry %s "%s" on site %s: %s',
                $entryId,
                $entryTitle ?: '(No title)',
                $siteId,
                $exception->getMessage()
            ), __METHOD__);
        }
    }

    /**
     * When performing normal save duties for the entry. update the entry's verification fields.
     *
     * @param Entry $entry
     * @return void
     * @see EventRegistrar::registerEntryLifecycle() // Element::EVENT_AFTER_SAVE
     */
    public function handleCanonicalSave(Entry $entry): void
    {
        /** @var Entry|VerifiableBehavior $entry */

        $entryId = $entry->getCanonicalId();

        try {
            $this->upsertEntryDetails(
                $entryId,
                $entry->siteId,
                $entry->getReviewerId(),
                $entry->getVerifiedUntilDate()
            );
        }
        catch (Exception $exception) {
            Craft::error(sprintf(
                'Error upserting "Verified Entries" details for entry %s "%s" on site %s: %s',
                $entryId,
                $entry->title,
                $entry->siteId,
                $exception->getMessage()
            ), __METHOD__);
        }

        $settings = VerifiedEntries::getInstance()->getSectionSettings();

        // Seed rows for any other supported sites that don't have a row yet.
        // This handles initial entry creation before propagation fires.
        foreach ($entry->getSupportedSites() as $siteInfo) {
            $siteId = is_array($siteInfo) ? ($siteInfo['siteId'] ?? null) : (int)$siteInfo;

            if (! $siteId || $siteId === $entry->siteId) {
                continue;
            }

            if ($this->hasVerificationRow($entryId, $siteId)) {
                continue;
            }

            // Apply this site's own section defaults — don't copy the canonical site's values
            $siteDefaults = $settings->getDefaultSettingsForSection($entry->sectionId, $siteId);
            [$siteReviewerId, $siteDefaultPeriod] = $siteDefaults ?? [null, null];

            $siteVerifiedUntilDate = null;
            if ($siteDefaultPeriod) {
                // TODO handle date exception
                $dateInterval = new DateInterval($siteDefaultPeriod);
                $siteVerifiedUntilDate = DateTimeHelper::now()->add($dateInterval);
            }

            try {
                $this->upsertEntryDetails(
                    $entryId,
                    $siteId,
                    $siteReviewerId,
                    $siteVerifiedUntilDate
                );
            }
            catch (Exception $exception) {
                Craft::error(sprintf(
                    'Error seeding verification row for entry %s "%s" on site %s: %s',
                    $entryId,
                    $entry->title,
                    $siteId,
                    $exception->getMessage()
                ), __METHOD__);
            }
        }
    }

    /**
     * After an entry saves and changes to the entry were detected, notify the entry's assigned
     * Reviewer if the changes were made by someone else.
     *
     * @param Entry $entry
     * @return void
     * @see EventRegistrar::registerEntryLifecycle() // Element::EVENT_AFTER_SAVE
     */
    public function handleCheckForChanges(Entry $entry): void
    {
        /** @var Entry|VerifiableBehavior $entry */

        if (! $entry->getHasVerifiedUntilDate() || ! $entry->enabled) {
            return;
        }

        $reviewer = $entry->getReviewer();
        if (! $reviewer || ! $reviewer->active) {
            Craft::info(sprintf(
                'Entry %s "%s" on site %s "%s" has no Reviewer to notify.',
                $entry->getCanonicalId(),
                $entry->title,
                $entry->siteId,
                $entry->getSite()->name
            ), __METHOD__);
            return;
        }

        if ($reviewer->id === Craft::$app->getUser()->getId()) {
            return;
        }

        // Email the Reviewer if someone else edits their assigned entry
        $isSent = $this->sendChangeNotification(
            $entry,
            new UserRecipient($reviewer)
        );

        if (! $isSent) {
            Craft::warning(
                "Failed to send 'change' notification to $reviewer->email.",
                __METHOD__
            );
        }
    }
}
