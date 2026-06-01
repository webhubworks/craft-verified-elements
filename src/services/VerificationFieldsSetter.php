<?php

namespace webhubworks\verifiedentries\services;

use craft\elements\Entry;
use craft\helpers\DateTimeHelper;
use DateInterval;
use DateTime;
use webhubworks\verifiedentries\base\VerificationFieldsSetterInterface;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\events\EventRegistrar;
use webhubworks\verifiedentries\services\singletons\SectionSettings;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Resolves the default Reviewer ID and "Verified until" date that should be applied to an entry's
 * verification fields before it is saved.
 *
 * @see EventRegistrar::registerEntryLifecycle() // Element::EVENT_BEFORE_SAVE
 * @see VerifiableBehavior::setReviewerId()
 * @see VerifiableBehavior::setVerifiedUntilDate()
 */
class VerificationFieldsSetter implements VerificationFieldsSetterInterface
{
    private ?int $defaultReviewerId;
    private ?string $defaultPeriod;

    public function __construct(
        int                        $sectionId,
        int                        $siteId,
        private readonly ?int      $currentReviewerId,
        private readonly ?DateTime $currentVerifiedUntilDate,
        private readonly bool      $isFirstSave,
        SectionSettings            $sectionSettings,
    )
    {
        $defaults = $sectionSettings->getDefaultSettingsForSection($sectionId, $siteId);
        [$this->defaultReviewerId, $this->defaultPeriod] = $defaults ?? [null, null];
    }

    /**
     * Instantiate this class from an `Entry` object.
     *
     * @param Entry $entry
     * @param SectionSettings $sectionSettings
     * @return self
     */
    public static function fromEntry(Entry $entry, SectionSettings $sectionSettings): self
    {
        /** @var Entry|VerifiableBehavior $entry */
        $isFirstSave = VerifiedEntries::getInstance()
            ->getVerification()
            ->isFirstSave(
                $entry->getCanonicalId(),
                $entry->siteId
            );

        return new self(
            sectionId: $entry->sectionId,
            siteId: $entry->siteId,
            currentReviewerId: $entry->getReviewerId(),
            currentVerifiedUntilDate: $entry->getVerifiedUntilDate(),
            isFirstSave: $isFirstSave,
            sectionSettings: $sectionSettings,
        );
    }

    /** @inheritDoc */
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

    /** @inheritDoc */
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

        // TODO handle date exception
        $dateInterval = new DateInterval($this->defaultPeriod);

        return DateTimeHelper::now()->add($dateInterval);
    }

    /** @inheritDoc */
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
