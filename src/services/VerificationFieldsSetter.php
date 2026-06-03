<?php

namespace webhubworks\verifiedentries\services;

use Carbon\Carbon;
use craft\elements\Entry;
use DateTime;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\db\PluginQuery;
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

        $recordAlreadyExists = PluginQuery::verifiableEntry(
            $entry->getCanonicalId(),
            $entry->siteId
        )->exists();

        return new self(
            sectionId: $entry->sectionId,
            siteId: $entry->siteId,
            currentReviewerId: $entry->getReviewerId(),
            currentVerifiedUntilDate: $entry->getVerifiedUntilDate(),
            isFirstSave: ! $recordAlreadyExists,
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
        if (! $this->defaultReviewerId) {
            return null;
        }

        if ($this->currentReviewerId !== null) {
            return null;
        }

        if ($this->currentVerifiedUntilDate === null) {
            return null;
        }

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
        if (! $this->isFirstSave) {
            return null;
        }

        if ($this->currentVerifiedUntilDate !== null) {
            return null;
        }

        if (! $this->defaultPeriod) {
            return null;
        }

        $dateInterval = DateHelper::createDateInterval($this->defaultPeriod);
        if ($dateInterval === null) {
            return null;
        }

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
