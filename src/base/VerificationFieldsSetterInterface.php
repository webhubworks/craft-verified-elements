<?php

namespace webhubworks\verifiedentries\base;

use craft\elements\Entry;
use DateTime;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\services\VerificationFieldsSetter;

/**
 * @see VerificationFieldsSetter
 */
interface VerificationFieldsSetterInterface
{
    /**
     * Returns the section's default Reviewer ID to apply to the entry (via the VerifiableBehavior
     * class), or null if none should be applied.
     *
     * Note: A default Reviewer is only applied when a verification date is set. An entry with an
     * "Indefinite" verification date that has no Reviewer set on it is acceptable.
     *
     * @return int|null
     */
    public function resolveReviewerId(): ?int;

    /**
     * Returns the section's default "Verified until" date value to apply to the entry (via the
     * VerifiableBehavior class), or null if none should be applied.
     *
     * Note: A default date is only applied on the entry's first save. "Indefinitely" is a valid
     * date-choice after that and should never be overwritten.
     *
     * @return DateTime|null
     */
    public function resolveVerificationDate(): ?DateTime;

    /**
     * Update an entry's "Reviewer" and "Verified until" fields before saving it.
     *
     * @param Entry $entry
     * @return Entry
     * @see VerifiableBehavior
     */
    public function updateEntryFields(Entry $entry): Entry;
}
