<?php

namespace webhubworks\verifiedelements\behaviors;

use Carbon\Carbon;
use Craft;
use craft\base\Element;
use craft\elements\User;
use DateTime;
use Exception;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\helpers\Log;
use yii\base\Behavior;

/**
 * This behavior provides additional properties and methods for Craft elements that have been
 * enabled for verification in the plugin's settings.
 *
 * @property Element $owner
 * @property-read bool $hasVerifiedUntilDate
 * @property null|mixed|int $reviewerId
 * @property-read bool $isVerified
 * @property null|mixed|DateTime $verifiedUntilDate
 * @property-read VerificationStatus $verificationStatus
 * @property-read User|null $reviewer
 */
class VerifiableBehavior extends Behavior
{
    public const NAME = 'verified-elements.verifiable';

    /**
     * @var bool Whether an asset's alt text was edited.
     */
    public bool $altChanged = false;


    // REVIEWER (Craft User element)
    // =============================================================================================

    private ?int $_reviewerId = null;

    /**
     * Get the Reviewer's ID.
     *
     * The "Reviewer" is a Craft User who has been assigned to review the element when its
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
     * The "Reviewer" is a Craft User who has been assigned to review the element when its
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
     * The "Reviewer" is a Craft User who has been assigned to review the element when its
     * "Verified Until" date expires.
     *
     * NOTE that this method does NOT memoize the User, so repeated calls means a new query to
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
        $systemTimeZone = DateHelper::createDateTimeZone();

        if ($value instanceof DateTime) {
            $this->_verifiedUntilDate = (clone $value)->setTimezone($systemTimeZone);
            return;
        }

        if (! DateHelper::isDateString($value)) {
            $this->_verifiedUntilDate = null;
            return;
        }

        if (DateHelper::isDateOnlyString($value)) {
            try {
                $this->_verifiedUntilDate = Carbon::createFromFormat(
                    'Y-m-d H:i:s', $value . ' 00:00:00',
                    $systemTimeZone
                );
            }
            catch (Exception $exception) {
                Log::error(
                    'Failed to parse date-only string',
                    $exception
                );
                $this->_verifiedUntilDate = null;
            }
            return;
        }

        // Datetime string from the DB — stored as UTC, convert to Craft's timezone
        $verifiedUntilDate = DateHelper::toDateTime($value);
        if ($verifiedUntilDate instanceof DateTime) {
            $this->_verifiedUntilDate = $verifiedUntilDate->setTimezone($systemTimeZone);
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
     * Returns the element's current verification status.
     *
     * @return VerificationStatus
     */
    public function getVerificationStatus(): VerificationStatus
    {
        return VerificationStatus::fromDate($this->getVerifiedUntilDate());
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
