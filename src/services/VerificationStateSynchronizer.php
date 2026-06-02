<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\elements\Entry;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\events\EventRegistrar;
use webhubworks\verifiedentries\mail\ChangeNotification;
use webhubworks\verifiedentries\models\UserRecipient;
use webhubworks\verifiedentries\services\singletons\SectionSettings;
use webhubworks\verifiedentries\services\singletons\Verification;
use yii\db\Exception;

/**
 * Persists an entry's verification state and notifies the assigned Reviewer when changes are
 * detected.
 *
 * @see EventRegistrar::registerEntryLifecycle() // Element::EVENT_AFTER_SAVE
 */
readonly class VerificationStateSynchronizer
{
    /**
     * @var Entry|VerifiableBehavior $entry
     * @noinspection PhpDocSignatureInspection
     */

    public function __construct(
        private Entry           $entry,
        private Verification    $verification,
        private SectionSettings $settings,
        private ?int            $currentUserId,
    ) {}

    /**
     * Checks if the entry's section is currently enabled in the plugin's settings for the entry's
     * currently-set site.
     *
     * @return bool If the entry's section is enabled.
     */
    public function isSectionEnabled(): bool
    {
        return $this->settings->isSectionEnabledForSite(
            $this->entry->sectionId,
            $this->entry->siteId,
        );
    }

    /**
     * Ensures a verification record exists for this entry and its currently-set site without
     * overwriting independently-set values.
     *
     * Note: only run this during `$entry->propagate`.
     *
     * @return bool If a record exists for this entry and its currently-set site.
     */
    public function ensurePropagatedRecord(): bool
    {
        $entryHasRowInDb = $this->verification->hasVerificationRow(
            $this->entry->getCanonicalId(),
            $this->entry->siteId
        );

        if ($entryHasRowInDb) {
            return true;
        }

        try {
            $this->verification->seedVerificationRow(
                $this->entry->getCanonicalId(),
                $this->entry->siteId
            );
        }
        catch (Exception $exception) {
            Craft::error(sprintf(
                'Error seeding verification row for entry %s "%s" on site %s: %s',
                $this->entry->getCanonicalId(),
                $this->entry->title,
                $this->entry->siteId,
                $exception->getMessage()
            ), __METHOD__);

            return false;
        }

        return true;
    }

    /**
     * Saves the entry's current verification state to the database.
     *
     * @return bool If the record for this entry was upserted successfully.
     */
    public function saveVerificationRecord(): bool
    {
        try {
            $this->verification->upsertEntryDetails(
                $this->entry->getCanonicalId(),
                $this->entry->siteId,
                $this->entry->getReviewerId(),
                $this->entry->getVerifiedUntilDate()
            );
        }
        catch (Exception $exception) {
            Craft::error(sprintf(
                'Error upserting "Verified Entries" details for entry %s "%s" on site %s: %s',
                $this->entry->getCanonicalId(),
                $this->entry->title,
                $this->entry->siteId,
                $exception->getMessage()
            ), __METHOD__);

            return false;
        }

        return true;
    }

    /**
     * Ensures a verification record exists for each of the entry's other supported sites.
     *
     * When an entry is first created, Craft hasn't fired propagation events yet for the other
     * sites. So this method loops over all the other sites the entry supports and creates a
     * verification record for each one that doesn't have one yet, applying that site's own
     * configured defaults rather than copying the canonical site's values.
     *
     * @return bool If there were no errors upserting rows for the entry's other supported sites.
     */
    public function ensureOtherSiteRecords(): bool
    {
        $errors = 0;

        foreach ($this->entry->getSupportedSites() as $siteInfo) {
            $siteId = is_array($siteInfo) ? ($siteInfo['siteId'] ?? null) : (int)$siteInfo;

            if (! $siteId || $siteId === $this->entry->siteId) {
                continue;
            }

            if (! $this->ensureSiteRecord($siteId)) {
                $errors++;
            }
        }

        return $errors === 0;
    }

    /**
     * When the entry gets updated by someone other than the assigned Reviewer, send the Reviewer
     * an email notifying them of the change.
     *
     * @return bool If the Reviewer was notified.
     */
    public function notifyReviewerOnChange(): bool
    {
        if (! $this->entry->getHasVerifiedUntilDate() || ! $this->entry->enabled) {
            return false;
        }

        $reviewer = $this->entry->getReviewer();
        if (! $reviewer || ! $reviewer->active) {
            Craft::info(sprintf(
                'Entry %s "%s" on site %s "%s" has no Reviewer to notify.',
                $this->entry->getCanonicalId(),
                $this->entry->title,
                $this->entry->siteId,
                $this->entry->getSite()->name
            ), __METHOD__);
            return false;
        }

        if ($reviewer->id === $this->currentUserId) {
            return false;
        }

        // Email the Reviewer if someone else edits their assigned entry
        $isSent = (new ChangeNotification(
            $this->entry,
            new UserRecipient($reviewer)
        ))->send();

        if (! $isSent) {
            Craft::warning(
                "Failed to send 'change' notification to $reviewer->email.",
                __METHOD__
            );
        }

        return $isSent;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Ensures a verification record exists for a single site supported by this entry using that
     * site's configured defaults.
     *
     * @param int $siteId
     * @return bool If the record for this entry was upserted successfully for the site.
     * @see ensureOtherSiteRecords()
     */
    private function ensureSiteRecord(int $siteId): bool
    {
        if ($this->verification->hasVerificationRow($this->entry->getCanonicalId(), $siteId)) {
            return true;
        }

        $sectionDefaults = $this->settings->getDefaultSettingsForSection(
            $this->entry->sectionId,
            $siteId
        );

        try {
            $this->verification->upsertEntryDetails(
                $this->entry->getCanonicalId(),
                $siteId,
                $sectionDefaults?->reviewerId,
                $this->verification->convertPeriodToDateTime(
                    $sectionDefaults?->period
                )
            );
        }
        catch (Exception $exception) {
            Craft::error(sprintf(
                'Error seeding verification row for entry %s "%s" on site %s: %s',
                $this->entry->getCanonicalId(),
                $this->entry->title,
                $siteId,
                $exception->getMessage()
            ), __METHOD__);

            return false;
        }

        return true;
    }
}
