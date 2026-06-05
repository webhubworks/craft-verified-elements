<?php

namespace webhubworks\verifiedentries\services;

use Carbon\Carbon;
use craft\elements\Entry;
use DateTime;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\db\PluginQuery;
use webhubworks\verifiedentries\enums\VerificationPeriod;
use webhubworks\verifiedentries\events\EventRegistrar;
use webhubworks\verifiedentries\helpers\DateHelper;
use webhubworks\verifiedentries\services\singletons\PluginSettings;

/**
 * Resolves the default Reviewer ID and "Verified until" date that should be applied to an entry's
 * verification fields before it is saved.
 *
 * @see EventRegistrar::registerEntryLifecycle() // Element::EVENT_BEFORE_SAVE
 * @see VerifiableBehavior::setReviewerId()
 * @see VerifiableBehavior::setVerifiedUntilDate()
 */
class VerificationFieldsSetter
{
    private ?int $defaultReviewerId;
    private ?string $defaultPeriod;

    public function __construct(
        int                        $sectionId,
        int                        $siteId,
        private readonly ?int      $currentReviewerId,
        private readonly ?DateTime $currentVerifiedUntilDate,
        private readonly bool      $isFirstSave,
        PluginSettings             $settings,
    )
    {
        $sectionDefaults = $settings->getDefaultSettingsForSection($sectionId, $siteId);
        $this->defaultReviewerId = $sectionDefaults?->reviewerId;
        $this->defaultPeriod = $sectionDefaults?->period;
    }

    /**
     * Instantiate this class from an `Entry` object.
     *
     * @param Entry $entry
     * @param PluginSettings $settings
     * @return self
     */
    public static function fromEntry(Entry $entry, PluginSettings $settings): self
    {
        /** @var Entry|VerifiableBehavior $entry */

        $canonicalId = $entry->getCanonicalId();
        $isFirstSave = true;

        if ($canonicalId !== null) {
            $isFirstSave = ! PluginQuery::verifiableEntry($canonicalId, $entry->siteId)->exists();
        }

        return new self(
            sectionId: $entry->sectionId,
            siteId: $entry->siteId,
            currentReviewerId: $entry->getReviewerId(),
            currentVerifiedUntilDate: $entry->getVerifiedUntilDate(),
            isFirstSave: $isFirstSave,
            settings: $settings,
        );
    }

    /**
     * Returns the section's default Reviewer ID to apply to the entry (via the VerifiableBehavior
     * class), or null if none should be applied.
     *
     * Note: A default Reviewer is only applied when a verification date is set. An entry with an
     * "Indefinite" verification date that has no Reviewer set on it is acceptable.
     *
     * @return int|null
     */
    public function resolveReviewerId(): ?int
    {
        // The section has no default reviewer to apply, so ignore.
        if (! $this->defaultReviewerId) {
            return null;
        }

        // The entry already has a Reviewer assigned, so ignore.
        if ($this->currentReviewerId !== null) {
            return null;
        }

        // The entry has no verification date right now, no date is about to be applied in
        // this same save, and the default period is not "Indefinitely" — so there's no
        // date context to assign a reviewer to.
        if (
            $this->currentVerifiedUntilDate === null &&
            $this->resolveVerificationDate() === null &&
            $this->defaultPeriod !== VerificationPeriod::Indefinitely->value
        ) {
            return null;
        }

        // There's a default reviewer, the entry doesn't have one yet, and a verification date
        // exists (or is about to), so apply the section's default Reviewer.
        return $this->defaultReviewerId;
    }

    /**
     * Returns the section's default "Verified until" date value to apply to the entry (via the
     * VerifiableBehavior class), or null if none should be applied.
     *
     * Note: A default date is only applied on the entry's first save. "Indefinitely" is a valid
     * date-choice after that and should never be overwritten.
     *
     * @return DateTime|null
     */
    public function resolveVerificationDate(): ?DateTime
    {
        // The entry already exists, so never apply the section's default period as the entry's
        // verification date.
        if (! $this->isFirstSave) {
            return null;
        }

        // The entry already has a verification date set on it, so ignore.
        if ($this->currentVerifiedUntilDate !== null) {
            return null;
        }

        // The section's plugin settings has no default period configured, so ignore.
        if (! $this->defaultPeriod) {
            return null;
        }

        // The default period is "Indefinitely", so no date to compute, but this is a valid
        // configured state. The caller should not treat this as "no date will be set."
        if ($this->defaultPeriod === VerificationPeriod::Indefinitely->value) {
            return null;
        }

        // The period is a string (like "P30D"), but parsing the value into a DateInterval object
        // failed (likely a corrupt or unrecognized value in the DB). Exit rather than throw.
        $dateInterval = DateHelper::createDateInterval($this->defaultPeriod);
        if ($dateInterval === null) {
            return null;
        }

        // It's the first save, there's no existing date, the section has a valid default period,
        // and it parsed cleanly. Return the default verification date.
        return Carbon::now()->add($dateInterval);
    }

    /**
     * Update an entry's "Reviewer" and "Verified until" fields before saving it.
     *
     * @param Entry $entry
     * @return Entry
     * @see VerifiableBehavior
     */
    public function updateEntryFields(Entry $entry): Entry
    {
        /** @var Entry|VerifiableBehavior $entry */

        if ($reviewerId = $this->resolveReviewerId()) {
            $entry->setReviewerId($reviewerId);
        }

        if ($date = $this->resolveVerificationDate()) {
            $entry->setVerifiedUntilDate($date);
        }

        return $entry;
    }
}
