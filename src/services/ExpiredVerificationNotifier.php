<?php

namespace webhubworks\verifiedelements\services;

use craft\elements\User;
use webhubworks\verifiedelements\base\NotifiableInterface;
use webhubworks\verifiedelements\console\controllers\CheckExpiredVerificationsController;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\events\EventRegistrar;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\mail\ExpiredNotification;
use webhubworks\verifiedelements\models\ElementData;

/**
 * Finds all elements whose verification date has passed and notifies either their assigned
 * Reviewers or the system email (if no Reviewer is assigned).
 *
 * @see CheckExpiredVerificationsController
 * @see EventRegistrar::registerEarlyEvents() // Gc::EVENT_RUN
 */
class ExpiredVerificationNotifier
{
    public function __construct(
        private readonly string $target
    ) {}

    /**
     * @var array<int, ElementData[]>|null
     */
    private ?array $expiredElementsByReviewerId = null;

    /**
     * @var ElementData[]|null
     */
    private ?array $expiredUnassignedElements = null;

    /**
     * Returns true if there are expired elements with Reviewers assigned to them.
     *
     * @return bool
     */
    public function hasExpiredElementsByReviewer(): bool
    {
        return ! empty($this->getExpiredElementsByReviewer());
    }

    /**
     * Returns true if there are expired elements with no Reviewer assigned.
     *
     * @return bool
     */
    public function hasUnassignedExpiredElements(): bool
    {
        return ! empty($this->getUnassignedExpiredElements());
    }

    /**
     * Returns an array of ElementData objects for elements with a verification date in the past,
     * but group the elements by their Reviewer user ID.
     *
     * @return array<int, ElementData[]>
     */
    public function getExpiredElementsByReviewer(): array
    {
        if ($this->expiredElementsByReviewerId === null) {
            $this->setExpiredElements();
        }

        return $this->expiredElementsByReviewerId;
    }

    /**
     * Return an array of ElementData objects for elements without a Reviewer assigned to them.
     *
     * @return ElementData[]
     */
    public function getUnassignedExpiredElements(): array
    {
        if ($this->expiredUnassignedElements === null) {
            $this->setExpiredElements();
        }

        return $this->expiredUnassignedElements;
    }

    /**
     * Get an active Reviewer by their Craft `User` ID.
     *
     * @param int $userId
     * @return User|null
     */
    public function getReviewer(int $userId): ?User
    {
        return User::find()->id($userId)->status('active')->one();
    }

    /**
     * Sends an expired verification digest email to the given recipient.
     *
     * @param NotifiableInterface $recipient
     * @param array $expiredElements
     * @return bool If the email was successfully sent.
     */
    public function notifyRecipient(NotifiableInterface $recipient, array $expiredElements): bool
    {
        $isSent = $this->buildExpiredNotification($expiredElements, $recipient)->send();

        if (! $isSent) {
            Log::warning(
                sprintf('Failed to send expired verification digest to %s.', $recipient->getEmail()),
                $this->target
            );
        }

        return $isSent;
    }

    /**
     * In the event a Reviewer can't be found when handling their expired elements (perhaps their
     * user account was deleted, deactivated, etc.), transfer them to the "unassigned" array so
     * they get handled when those elements are reported to the system admin.
     *
     * @param int $reviewerId
     * @return bool If the elements were successfully reassigned.
     */
    public function reassignElementsToUnassigned(int $reviewerId): bool
    {
        $orphanedElements = $this->getExpiredElementsByReviewer()[$reviewerId] ?? [];
        if (empty($orphanedElements)) {
            return false;
        }

        array_push($this->expiredUnassignedElements, ...$orphanedElements);

        return true;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Queries for all expired elements and populates the internal reviewer and unassigned element
     * arrays.
     *
     * @return void
     */
    protected function setExpiredElements(): void
    {
        $this->expiredElementsByReviewerId = [];
        $this->expiredUnassignedElements = [];

        foreach (PluginQuery::expiredVerifiableElements(ElementType::enabledTypes())->all() as $record) {
            $elementData = ElementData::fromArray($record);

            if ($elementData->reviewerId === null) {
                $this->expiredUnassignedElements[] = $elementData;
                continue;
            }

            $this->expiredElementsByReviewerId[$elementData->reviewerId][] = $elementData;
        }
    }

    /**
     * Factory method for testing.
     *
     * @param array $expiredElements
     * @param NotifiableInterface $recipient
     * @return ExpiredNotification
     */
    protected function buildExpiredNotification(array $expiredElements, NotifiableInterface $recipient): ExpiredNotification
    {
        return new ExpiredNotification($expiredElements, $recipient);
    }
}
