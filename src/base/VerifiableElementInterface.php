<?php

namespace webhubworks\verifiedelements\base;

use craft\elements\User;
use DateTime;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\enums\VerificationStatus;

/**
 * Describes the verification API that VerifiableBehavior adds to an element.
 *
 * No element class actually implements this interface - the methods reach elements at
 * runtime through behavior attachment. It exists for static analysis: docblocks can type a
 * behavior-carrying element as `Element&VerifiableElementInterface`, which PHPStan and
 * PhpStorm can resolve. (Intersecting with the behavior class itself is an impossible type,
 * since a PHP object can never be an instance of two unrelated classes.)
 *
 * VerifiableBehavior implements this interface, so PHP itself keeps the contract and the
 * real signatures in sync.
 *
 * @see VerifiableBehavior
 */
interface VerifiableElementInterface
{
    // REVIEWER (Craft User element)
    // =============================================================================================

    /**
     * Get the Reviewer's ID.
     *
     * The "Reviewer" is a Craft User who has been assigned to review the element when its
     * "Verified Until" date expires.
     *
     * @return int|null
     */
    public function getReviewerId(): ?int;

    /**
     * Set the Reviewer's user ID.
     *
     * The "Reviewer" is a Craft User who has been assigned to review the element when its
     * "Verified Until" date expires.
     *
     * @param mixed $value
     * @return void
     */
    public function setReviewerId(mixed $value): void;

    /**
     * Get the Reviewer's User object.
     *
     * The "Reviewer" is a Craft User who has been assigned to review the element when its
     * "Verified Until" date expires.
     *
     * NOTE that this method does NOT memoize the User, so repeated calls means a new query to
     * the database. If you call this, save it to a variable for reuse.
     *
     * @return User|null
     */
    public function getReviewer(): ?User;


    // VERIFICATION DATE ("Verified until" select field)
    // =============================================================================================

    /**
     * Get the "Verified Until" select field's value.
     *
     * @return DateTime|null
     */
    public function getVerifiedUntilDate(): ?DateTime;

    /**
     * Set the "Verified Until" select field's value.
     *
     * @param mixed $value Any value that can be converted to a DateTime object.
     * @return void
     */
    public function setVerifiedUntilDate(mixed $value): void;

    /**
     * Checks if the "Verified Until" select field has a value other than null.
     *
     * @return bool
     */
    public function getHasVerifiedUntilDate(): bool;

    /**
     * Returns the element's current verification status.
     *
     * @return VerificationStatus
     */
    public function getVerificationStatus(): VerificationStatus;

    /**
     * Checks if the "Verified until" select field's value is still in the future. If the value is
     * null, this returns true because the value means "indefinitely".
     *
     * @return bool
     */
    public function getIsVerified(): bool;


    // ASSETS
    // =============================================================================================

    /**
     * Returns whether an asset's alt text was edited.
     *
     * @return bool
     */
    public function getIsAltChanged(): bool;

    /**
     * Set whether an asset's alt text was edited.
     *
     * @param bool $value
     * @return void
     */
    public function setIsAltChanged(bool $value): void;
}
