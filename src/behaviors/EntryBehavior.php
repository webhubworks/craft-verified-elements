<?php

namespace webhubworks\verifiedentries\behaviors;

use Craft;
use craft\base\Element;
use craft\elements\Entry;
use craft\elements\User;
use craft\events\ModelEvent;
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use DateTime;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Behavior;
use yii\db\Exception;

/**
 * This behavior provides additional properties and methods for Craft entries that have been
 * enabled for verification in the plugin's settings.
 *
 * @property Entry $owner
 * @property-read bool $hasVerifiedUntilDate
 * @property null|mixed|int $reviewerId
 * @property-read bool $isVerified
 * @property-read bool $isSectionEnabledForVerification
 * @property null|mixed|DateTime $verifiedUntilDate
 * @property-read User|null $reviewer
 */
class EntryBehavior extends Behavior
{
    // EVENTS
    // =============================================================================================

    /** @inheritdoc */
    public function events(): array
    {
        return [
            Element::EVENT_AFTER_SAVE => 'afterSave',
        ];
    }

    /**
     * Run additional logic after the entry is saved.
     *
     * @param ModelEvent $event
     */
    public function afterSave(ModelEvent $event): void
    {
        /** @var Entry $entry */
        $entry = $event->sender;

        if (ElementHelper::isDraftOrRevision($entry)) {
            return;
        }

        $verification = VerifiedEntries::getInstance()->getVerification();
        $entryId = $entry->getCanonicalId();

        // On propagation, only seed the row if one doesn't exist yet.
        // This prevents a save on one site from overwriting verification
        // settings that were independently set on another site.
        if ($entry->propagating) {
            if (! $verification->hasVerificationRow($entryId, $entry->siteId)) {
                try {
                    $verification->seedVerificationRow($entryId, $entry->siteId);
                }
                catch (Exception $exception) {
                    Craft::error(sprintf(
                        'Error seeding verification row for entry %s "%s" on site %s: %s',
                        $entryId,
                        $entry->title,
                        $entry->siteId,
                        $exception->getMessage()
                    ), __METHOD__);
                }
            }

            return;
        }

        if (! $this->getIsSectionEnabledForVerification()) {
            return;
        }

        try {
            $verification->upsertEntryDetails(
                $entryId,
                $entry->siteId,
                $this->getReviewerId(),
                $this->getVerifiedUntilDate()
            );
        }
        catch (Exception $exception) {
            Craft::error(sprintf(
                'Error upserting "Verified Entries" details for entry %s "%s" on site %s: %s',
                $entryId,
                $entry->title,
                $entry->siteId,
                $exception->getMessage()
            ), __METHOD__);
        }

        // Seed rows for any other supported sites that don't have a row yet.
        // This handles initial entry creation before propagation fires.
        foreach ($entry->getSupportedSites() as $siteInfo) {
            $siteId = is_array($siteInfo) ? ($siteInfo['siteId'] ?? null) : (int)$siteInfo;

            if (! $siteId || $siteId === $entry->siteId) {
                continue;
            }

            if (! $verification->hasVerificationRow($entryId, $siteId)) {
                try {
                    $verification->upsertEntryDetails(
                        $entryId,
                        $siteId,
                        $this->getReviewerId(),
                        $this->getVerifiedUntilDate()
                    );
                }
                catch (Exception $exception) {
                    Craft::error(sprintf(
                        'Error seeding verification row for entry %s "%s" on site %s: %s',
                        $entryId,
                        $entry->title,
                        $siteId,
                        $exception->getMessage()
                    ), __METHOD__);
                }
            }
        }
    }


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
            $this->_reviewerId = (int)$value;
            return;
        }

        if ($value instanceof User) {
            $this->_reviewerId = $value->id;
            return;
        }

        if (is_array($value)) {
            $this->_reviewerId = ! empty($value) ? (int) reset($value) : null;
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
        // TODO handle toDatetime exception
        $verifiedUntilDate = DateTimeHelper::toDatetime($value, true);

        if ($verifiedUntilDate instanceof DateTime) {
            $this->_verifiedUntilDate = $verifiedUntilDate;
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
     * Checks if the "Verified until" select field's value is still in the future. If the value is
     * null, this returns true because the value means "indefinitely".
     *
     * @return bool
     */
    public function getIsVerified(): bool
    {
        if ($this->_verifiedUntilDate === null) {
            return true;
        }

        return $this->_verifiedUntilDate > new DateTime();
    }

    /**
     * Checks if the entry's section has been enabled for verification in the plugin's settings.
     *
     * @return bool
     */
    public function getIsSectionEnabledForVerification(): bool
    {
        return VerifiedEntries::getInstance()
            ->getSectionSettings()
            ->getIsEnabledForSection(
                $this->owner->sectionId,
                $this->owner->siteId,
            );
    }
}
