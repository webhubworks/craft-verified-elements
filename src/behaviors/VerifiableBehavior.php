<?php

namespace webhubworks\verifiedelements\behaviors;

use Carbon\Carbon;
use Craft;
use craft\base\Element;
use craft\elements\User;
use DateTime;
use Exception;
use webhubworks\verifiedelements\base\VerifiableElementInterface;
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
 * @property bool $isAltChanged
 * @property-read User|null $reviewer
 */
class VerifiableBehavior extends Behavior implements VerifiableElementInterface
{
    public const NAME = 'verified-elements.verifiable';


    // REVIEWER (Craft User element)
    // =============================================================================================

    private ?int $_reviewerId = null;

    /** @inheritDoc */
    public function getReviewerId(): ?int
    {
        return $this->_reviewerId;
    }

    /** @inheritDoc */
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
            $this->_reviewerId = !empty($value) ? (int)reset($value) : null;
            return;
        }

        $this->_reviewerId = null;
    }

    /** @inheritDoc */
    public function getReviewer(): ?User
    {
        if (!$this->getReviewerId()) {
            return null;
        }

        return Craft::$app->getUsers()->getUserById($this->getReviewerId());
    }


    // VERIFICATION DATE ("Verified until" select field)
    // =============================================================================================

    private ?DateTime $_verifiedUntilDate = null;

    /** @inheritDoc */
    public function setVerifiedUntilDate(mixed $value): void
    {
        $systemTimeZone = DateHelper::createDateTimeZone();

        if ($value instanceof DateTime) {
            $this->_verifiedUntilDate = (clone $value)->setTimezone($systemTimeZone);
            return;
        }

        if (!DateHelper::isDateString($value)) {
            $this->_verifiedUntilDate = null;
            return;
        }

        if (DateHelper::isDateOnlyString($value)) {
            try {
                $this->_verifiedUntilDate = Carbon::createFromFormat(
                    'Y-m-d H:i:s', $value . ' 00:00:00',
                    $systemTimeZone
                );
            } catch (Exception $exception) {
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

    /** @inheritDoc */
    public function getVerifiedUntilDate(): ?DateTime
    {
        return $this->_verifiedUntilDate;
    }

    /** @inheritDoc */
    public function getHasVerifiedUntilDate(): bool
    {
        return $this->_verifiedUntilDate !== null;
    }

    /** @inheritDoc */
    public function getVerificationStatus(): VerificationStatus
    {
        return VerificationStatus::fromDate($this->getVerifiedUntilDate());
    }

    /** @inheritDoc */
    public function getIsVerified(): bool
    {
        return $this->getVerificationStatus() !== VerificationStatus::Expired;
    }


    // ASSETS
    // =============================================================================================

    private bool $altChanged = false;

    /** @inheritDoc */
    public function getIsAltChanged(): bool
    {
        return $this->altChanged;
    }

    /** @inheritDoc */
    public function setIsAltChanged(bool $value): void
    {
        $this->altChanged = $value;
    }
}
