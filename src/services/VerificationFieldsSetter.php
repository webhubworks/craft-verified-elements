<?php

namespace webhubworks\verifiedelements\services;

use craft\base\Element;
use DateTime;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\VerificationPeriod;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\services\singletons\PluginSettings;

/**
 * Resolves the default Reviewer ID and "Verified until" date that should be applied to an
 * elements's verification fields before it is saved.
 *
 * @see EventRegistrar::onBeforeSaveAsset()
 * @see EventRegistrar::onBeforeSaveEntry()
 * @see VerifiableBehavior::setReviewerId()
 * @see VerifiableBehavior::setVerifiedUntilDate()
 */
class VerificationFieldsSetter
{
    private ?int $defaultReviewerId;
    private ?string $defaultPeriod;

    public function __construct(
        int                        $containerId,
        int                        $siteId,
        string                     $elementType,
        private readonly ?int      $currentReviewerId,
        private readonly ?DateTime $currentVerifiedUntilDate,
        private readonly bool      $isFirstSave,
        PluginSettings             $settings,
    ) {
        $containerDefaults = $settings->getDefaultSettingsForContainer(
            $containerId,
            $siteId,
            $elementType
        );

        $this->defaultReviewerId = $containerDefaults?->reviewerId;
        $this->defaultPeriod = $containerDefaults?->period;
    }

    /**
     * Instantiate this class from a live element (with VerifiableBehavior attached).
     *
     * @param Element|VerifiableBehavior $element
     * @param PluginSettings $settings
     * @return self
     */
    public static function fromElement(Element $element, PluginSettings $settings): self
    {
        /** @var Element|VerifiableBehavior $element */

        $elementType = ElementType::fromElement($element);

        $canonicalId = $element->getCanonicalId();
        $isFirstSave = true;

        if ($canonicalId !== null) {
            $isFirstSave = !PluginQuery::verifiableEntry($canonicalId, $element->siteId)->exists();
        }

        return new self(
            containerId: $elementType->containerId($element),
            siteId: $element->siteId,
            elementType: $elementType->value,
            currentReviewerId: $element->getReviewerId(),
            currentVerifiedUntilDate: $element->getVerifiedUntilDate(),
            isFirstSave: $isFirstSave,
            settings: $settings,
        );
    }

    /**
     * Returns the container's default Reviewer ID to apply to the element (via the VerifiableBehavior
     * class), or null if none should be applied.
     *
     * Note: A default Reviewer is only applied when a verification date is set. An element with
     * an "Indefinite" verification date that has no Reviewer set on it is acceptable.
     *
     * @return int|null
     */
    public function resolveReviewerId(): ?int
    {
        // The container has no default reviewer to apply, so ignore.
        if (!$this->defaultReviewerId) {
            return null;
        }

        // The element already has a Reviewer assigned, so ignore.
        if ($this->currentReviewerId !== null) {
            return null;
        }

        // The element has no verification date right now, no date is about to be applied in
        // this same save, and the default period is not "Indefinitely,"so there's no
        // date context to assign a reviewer to.
        if (
            $this->currentVerifiedUntilDate === null &&
            $this->resolveVerificationDate() === null &&
            $this->defaultPeriod !== VerificationPeriod::Indefinitely->value
        ) {
            return null;
        }

        // There's a default reviewer, the element doesn't have one yet, and a verification date
        // exists (or is about to), so apply the container's default Reviewer.
        return $this->defaultReviewerId;
    }

    /**
     * Returns the container's default "Verified until" date value to apply to the element (via the
     * VerifiableBehavior class), or null if none should be applied.
     *
     * Note: A default date is only applied on the element's first save. "Indefinitely" is a valid
     * date-choice after that and should never be overwritten.
     *
     * @return DateTime|null
     */
    public function resolveVerificationDate(): ?DateTime
    {
        // The element already exists, so never apply the container's default period as the element's
        // verification date.
        if (!$this->isFirstSave) {
            return null;
        }

        // The element already has a verification date set on it, so ignore.
        if ($this->currentVerifiedUntilDate !== null) {
            return null;
        }

        // The container's plugin settings has no default period configured, so ignore.
        if (!$this->defaultPeriod) {
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

        // It's the first save, there's no existing date, the container has a valid default period,
        // and it parsed cleanly. Return the default verification date.
        return DateHelper::now()->add($dateInterval);
    }

    /**
     * Update an element's "Reviewer" and "Verified until" fields before saving it.
     *
     * @param Element $element
     * @return Element
     * @see VerifiableBehavior
     */
    public function updateElementFields(Element $element): Element
    {
        /** @var Element|VerifiableBehavior $element */

        if ($reviewerId = $this->resolveReviewerId()) {
            $element->setReviewerId($reviewerId);
        }

        if ($date = $this->resolveVerificationDate()) {
            $element->setVerifiedUntilDate($date);
        }

        return $element;
    }
}
