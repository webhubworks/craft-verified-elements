<?php

namespace webhubworks\verifiedentries\behaviors;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use DateTime;
use DateTimeZone;
use webhubworks\verifiedentries\enums\VerificationStatus;
use yii\base\Behavior;

/**
 * This behavior provides additional properties and methods for Craft entries that have been
 * enabled for verification in the plugin's settings.
 *
 * @property Entry $owner
 * @property-read bool $hasVerifiedUntilDate
 * @property null|mixed|int $reviewerId
 * @property-read bool $isVerified
 * @property null|mixed|DateTime $verifiedUntilDate
 * @property-read VerificationStatus $verificationStatus
 * @property-read User|null $reviewer
 */
class VerifiableBehavior extends Behavior
{
    public const NAME = 'verified-entries.verifiable';


    // REVIEWER (Craft User element)
    // =============================================================================================

    private ?int $_reviewerId = null;

    /**
     * Get the Reviewer's ID.
     *
     * The "Reviewer" is a Craft User who has been assigned to review the entry when its
     * "Verified Until" date expires.
     *
     * @return int|null
     */
    public function getReviewerId(): ?int
    {
        return $this->_reviewerId;
    }

    /**
     * Set the Reviewer's user ID.
     *
     * The "Reviewer" is a Craft User who has been assigned to review the entry when its
     * "Verified Until" date expires.
     *
     * @param mixed $value
     * @return void
     */
    public function setReviewerId(mixed $value): void
    {
        if (is_int($value)) {
            $this->_reviewerId = $value;
            return;
        }

        if (is_string($value)) {
            $this->_reviewerId = (int)$value ?: null;
            return;
        }

        if ($value instanceof User) {
            $this->_reviewerId = $value->id;
            return;
        }

        if (is_array($value)) {
            $this->_reviewerId = ! empty($value) ? (int)reset($value) : null;
            return;
        }

        $this->_reviewerId = null;
    }

    /**
     * Get the Reviewer's User object.
     *
     * The "Reviewer" is a Craft User who has been assigned to review the entry when its
     * "Verified Until" date expires.
     *
     * NOTE that this method does NOT memorize the User, so repeated calls means a new query to
     * the database. If you call this, save it to a variable for reuse.
     *
     * @return User|null
     */
    public function getReviewer(): ?User
    {
        if (! $this->getReviewerId()) {
            return null;
        }

        return Craft::$app->getUsers()->getUserById($this->getReviewerId());
    }


    // VERIFICATION DATE ("Verified until" select field)
    // =============================================================================================

    private ?DateTime $_verifiedUntilDate = null;

    /**
     * Set the "Verified Until" select field's value.
     *
     * @param mixed $value Any value that can be converted to a DateTime object.
     * @return void
     */
    public function setVerifiedUntilDate(mixed $value): void
    {
        // TODO handle date exception
        $craftTimezone = new DateTimeZone(Craft::$app->getTimeZone());

        if ($value instanceof DateTime) {
            $this->_verifiedUntilDate = (clone $value)->setTimezone($craftTimezone);
            return;
        }

        if (! $value) {
            $this->_verifiedUntilDate = null;
            return;
        }

        if (is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            // Date-only string from a form post — midnight in Craft's timezone
            $this->_verifiedUntilDate = new DateTime($value . ' 00:00:00', $craftTimezone);
            return;
        }

        // Datetime string from the DB — stored as UTC, convert to Craft's timezone
        $verifiedUntilDate = DateTimeHelper::toDatetime($value);
        if ($verifiedUntilDate instanceof DateTime) {
            $this->_verifiedUntilDate = $verifiedUntilDate->setTimezone($craftTimezone);
            return;
        }

        $this->_verifiedUntilDate = null;
    }

    /**
     * Get the "Verified Until" select field's value.
     *
     * @return DateTime|null
     */
    public function getVerifiedUntilDate(): ?DateTime
    {
        return $this->_verifiedUntilDate;
    }

    /**
     * Checks if the "Verified Until" select field has a value other than null.
     *
     * @return bool
     */
    public function getHasVerifiedUntilDate(): bool
    {
        return $this->_verifiedUntilDate !== null;
    }

    /**
     * Returns the entry's current verification status.
     *
     * Note: this returns an enum. You'll need to call "->handle()", "->label()", or "->color()"
     * for the value you want.
     *
     * @return VerificationStatus
     */
    public function getVerificationStatus(): VerificationStatus
    {
        if ($this->_verifiedUntilDate === null) {
            return VerificationStatus::Indefinite;
        }

        // TODO handle date exception
        $now = new DateTime('now', new DateTimeZone(Craft::$app->getTimeZone()));

        if ($this->_verifiedUntilDate <= $now) {
            return VerificationStatus::Expired;
        }

        if ($this->_reviewerId === null) {
            return VerificationStatus::Unassigned;
        }

        return VerificationStatus::Verified;
    }

    /**
     * Checks if the "Verified until" select field's value is still in the future. If the value is
     * null, this returns true because the value means "indefinitely".
     *
     * @return bool
     */
    public function getIsVerified(): bool
    {
        return $this->getVerificationStatus() !== VerificationStatus::Expired;
    }
}
