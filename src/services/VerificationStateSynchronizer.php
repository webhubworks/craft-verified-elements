<?php

namespace webhubworks\verifiedelements\services;

use Craft;
use craft\db\Query;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Db;
use DateTime;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\db\PluginTable;
use webhubworks\verifiedelements\elements\VerifiedEntry;
use webhubworks\verifiedelements\events\EventRegistrar;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\mail\ChangeNotification;
use webhubworks\verifiedelements\models\ElementData;
use webhubworks\verifiedelements\models\UserRecipient;
use webhubworks\verifiedelements\services\singletons\PluginSettings;
use yii\db\Exception;

/**
 * Persists an element's verification state and notifies the assigned Reviewer when someone else
 * saves the element.
 *
 * @see EventRegistrar::registerEntryLifecycle() // Element::EVENT_AFTER_SAVE
 */
class VerificationStateSynchronizer
{
    public function __construct(
        private readonly ElementData    $elementData,
        private readonly array          $supportedSiteIds,
        private readonly bool           $isElementEnabled,
        private readonly PluginSettings $settings,
        private readonly ?int           $currentUserId,
    ) {}

    /**
     * Ensures a verification record exists for this element and its currently-set site without
     * overwriting independently-set values.
     *
     * Note: only call this while the element is propagating ($element->propagating is true).
     *
     * @return bool If a record exists for this element and its currently-set site.
     */
    public function ensurePropagatedRecord(): bool
    {
        $recordAlreadyExists = PluginQuery::verifiableEntry(
            $this->elementData->id,
            $this->elementData->siteId
        )->exists();

        if ($recordAlreadyExists) {
            return true;
        }

        try {
            $this->copyRecordToSite(
                $this->elementData->id,
                $this->elementData->siteId
            );
        }
        catch (Exception $exception) {
            Log::error(sprintf(
                'Error seeding verification row for %s [%s] "%s" on site %s',
                Log::element($this->elementData->type),
                $this->elementData->id,
                $this->elementData->title,
                $this->elementData->siteId
            ), $exception);

            return false;
        }

        return true;
    }

    /**
     * Saves the element's current verification state to the database.
     *
     * @return bool If the record was saved successfully.
     */
    public function saveVerificationRecord(): bool
    {
        try {
            Db::upsert(PluginTable::ATTRIBUTES, [
                'elementId' => $this->elementData->id,
                'siteId' => $this->elementData->siteId,
                'reviewerId' => $this->elementData->reviewerId,
                'verifiedUntilDate' => $this->elementData->verifiedUntilDate,
            ], [
                'reviewerId' => $this->elementData->reviewerId,
                'verifiedUntilDate' => $this->elementData->verifiedUntilDate,
            ]);
        }
        catch (Exception $exception) {
            Log::error(sprintf(
                'Error upserting verification details for %s [%s] "%s" on site %s',
                Log::element($this->elementData->type),
                $this->elementData->id,
                $this->elementData->title,
                $this->elementData->siteId
            ), $exception);

            return false;
        }

        // Saving an element in Craft only busts caches for the vanilla elements
        // (Entry, Asset, etc.), so we need to explicitly bust the cache for the verified elements
        // (VerifiedEntry, VerifiedAsset, etc.).
        $verifiedElementType = match ($this->elementData->type) {
            Entry::class => VerifiedEntry::class,
        };
        Craft::$app->getElements()->invalidateCachesForElementType($verifiedElementType);

        return true;
    }

    /**
     * Ensures a verification record exists for each of the element's other supported sites.
     *
     * When an element is first created, Craft hasn't fired propagation events yet for the other
     * sites. So this method loops over all the other sites the element supports and creates a
     * verification record for each one that doesn't have one yet, applying that site's own
     * configured defaults rather than copying the canonical site's values.
     *
     * @return bool If there were no errors upserting rows for the element's other supported sites.
     */
    public function ensureOtherSiteRecords(): bool
    {
        $errors = 0;

        foreach ($this->supportedSiteIds as $siteId) {
            if ($siteId === $this->elementData->siteId) {
                continue;
            }

            if (! $this->ensureSiteRecord($siteId)) {
                $errors++;
            }
        }

        return $errors === 0;
    }

    /**
     * When the element gets updated by someone other than the assigned Reviewer, send the Reviewer
     * an email notifying them of the change.
     *
     * Note: this skips disabled elements and elements verified 'Indefinitely'.
     *
     * @return bool If the Reviewer was notified.
     */
    public function notifyReviewerOnChange(): bool
    {
        if ($this->elementData->verifiedUntilDate === null || ! $this->isElementEnabled) {
            return false;
        }

        $reviewer = $this->findReviewer();
        if (! $reviewer || ! $reviewer->active) {
            Log::warning(sprintf(
                '%s [%s] "%s" on site %s "%s" has no Reviewer to notify.',
                Log::element($this->elementData->type),
                $this->elementData->id,
                $this->elementData->title,
                $this->elementData->siteId,
                $this->elementData->siteName
            ), __METHOD__);
            return false;
        }

        if ($reviewer->id === $this->currentUserId) {
            return false;
        }

        // Email the Reviewer if someone else edits their assigned element
        $isSent = $this->buildChangeNotification($reviewer)->send();

        if (! $isSent) {
            Log::warning(
                "Failed to send 'change' notification to $reviewer->email.",
                __METHOD__
            );
        }

        return $isSent;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Ensures a verification record exists for a single site supported by this element using
     * that site's configured defaults.
     *
     * @param int $siteId
     * @return bool If the record for this element was upserted successfully for the site.
     * @see ensureOtherSiteRecords()
     */
    private function ensureSiteRecord(int $siteId): bool
    {
        $recordAlreadyExists = PluginQuery::verifiableEntry(
            $this->elementData->id,
            $siteId
        )->exists();

        if ($recordAlreadyExists) {
            return true;
        }

        $sectionDefaults = $this->settings->getDefaultSettingsForSection(
            $this->elementData->containerId,
            $siteId
        );

        $verifiedUntilDate = $this->convertPeriodToDateTime(
            $sectionDefaults?->period
        );

        try {
            Db::upsert(PluginTable::ATTRIBUTES, [
                'elementId' => $this->elementData->id,
                'siteId' => $siteId,
                'reviewerId' => $sectionDefaults?->reviewerId,
                'verifiedUntilDate' => Db::prepareDateForDb($verifiedUntilDate),
            ], [
                'reviewerId' => $sectionDefaults?->reviewerId,
                'verifiedUntilDate' => Db::prepareDateForDb($verifiedUntilDate),
            ]);
        }
        catch (Exception $exception) {
            Log::error(sprintf(
                'Error seeding verification row for %s [%s] "%s" on site %s',
                Log::element($this->elementData->type),
                $this->elementData->id,
                $this->elementData->title,
                $siteId
            ), $exception);

            return false;
        }

        return true;
    }

    /**
     * Returns a DateTime that's offset from now by the given verification period interval.
     *
     * Null is returned if no period is given or if it can't be parsed into an interval.
     *
     * @param string|null $period
     * @return DateTime|null
     * @see VerificationPeriod
     */
    private function convertPeriodToDateTime(?string $period): ?DateTime
    {
        if (! $period) {
            return null;
        }

        $dateInterval = DateHelper::createDateInterval($period);
        if ($dateInterval === null) {
            return null;
        }

        return DateHelper::now()->add($dateInterval);
    }

    /**
     * Copies from the element's first existing verification record (of any site) to a new site.
     * Does nothing if no source record exists for the element.
     *
     * @param int $elementId
     * @param int $siteId
     * @return void
     * @throws Exception
     */
    private function copyRecordToSite(int $elementId, int $siteId): void
    {
        $sourceRow = (new Query())
            ->select(['reviewerId', 'verifiedUntilDate'])
            ->from(PluginTable::ATTRIBUTES)
            ->where(['elementId' => $elementId])
            ->one();

        if (! $sourceRow) {
            return;
        }

        $verifiedUntilDate = null;
        if (isset($sourceRow['verifiedUntilDate'])) {
            $verifiedUntilDate = DateHelper::toDateTime($sourceRow['verifiedUntilDate']);
        }

        Db::upsert(PluginTable::ATTRIBUTES, [
            'elementId' => $elementId,
            'siteId' => $siteId,
            'reviewerId' => $sourceRow['reviewerId'],
            'verifiedUntilDate' => Db::prepareDateForDb($verifiedUntilDate),
        ], [
            'reviewerId' => $sourceRow['reviewerId'],
            'verifiedUntilDate' => Db::prepareDateForDb($verifiedUntilDate),
        ]);
    }

    /**
     * Factory method for testing.
     *
     * @return User|null
     */
    protected function findReviewer(): ?User
    {
        return $this->elementData->getReviewer();
    }

    /**
     * Factory method for testing.
     *
     * @param User $reviewer
     * @return ChangeNotification
     */
    protected function buildChangeNotification(User $reviewer): ChangeNotification
    {
        return new ChangeNotification($this->elementData, new UserRecipient($reviewer));
    }
}
