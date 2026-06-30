<?php

namespace webhubworks\verifiedelements\services;

use craft\elements\User;
use webhubworks\verifiedelements\base\NotifiableInterface;
use webhubworks\verifiedelements\console\controllers\CheckExpiredVerificationsController;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\events\EventRegistrar;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\mail\ExpiredNotification;
use webhubworks\verifiedelements\models\ExpiredEntryData;

/**
 * Finds all entries whose verification date has passed and notifies either their assigned
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
     * @var array<int, ExpiredEntryData[]>|null
     */
    private ?array $expiredEntriesByReviewerId = null;

    /**
     * @var ExpiredEntryData[]|null
     */
    private ?array $expiredUnassignedEntries = null;

    /**
     * Returns true if there are expired entries with Reviewers assigned to them.
     *
     * @return bool
     */
    public function hasExpiredEntriesByReviewer(): bool
    {
        return ! empty($this->getExpiredEntriesByReviewer());
    }

    /**
     * Returns true if there are expired entries with no Reviewer assigned.
     *
     * @return bool
     */
    public function hasUnassignedExpiredEntries(): bool
    {
        return ! empty($this->getUnassignedExpiredEntries());
    }

    /**
     * Returns an array of ExpiredEntryData objects for entries with a verification date in the past,
     * but group the entries by their Reviewer user ID.
     *
     * @return array<int, ExpiredEntryData[]>
     */
    public function getExpiredEntriesByReviewer(): array
    {
        if ($this->expiredEntriesByReviewerId === null) {
            $this->setExpiredEntries();
        }

        return $this->expiredEntriesByReviewerId;
    }

    /**
     * Return an array of ExpiredEntryData objects for entries without a Reviewer assigned to them.
     *
     * @return ExpiredEntryData[]
     */
    public function getUnassignedExpiredEntries(): array
    {
        if ($this->expiredUnassignedEntries === null) {
            $this->setExpiredEntries();
        }

        return $this->expiredUnassignedEntries;
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
     * @param array $expiredEntries
     * @return bool If the email was successfully sent.
     */
    public function notifyRecipient(NotifiableInterface $recipient, array $expiredEntries): bool
    {
        $isSent = $this->buildExpiredNotification($expiredEntries, $recipient)->send();

        if (! $isSent) {
            Log::warning(
                sprintf('Failed to send expired verification digest to %s.', $recipient->getEmail()),
                $this->target
            );
        }

        return $isSent;
    }

    /**
     * In the event a Reviewer can't be found when handling their expired entries (perhaps their
     * user account was deleted, deactivated, etc.), transfer them to the "unassigned" array so
     * they get handled when those entries are reported to the system admin.
     *
     * @param int $reviewerId
     * @return bool If the entries were successfully reassigned.
     */
    public function reassignEntriesToUnassigned(int $reviewerId): bool
    {
        $orphanedEntries = $this->getExpiredEntriesByReviewer()[$reviewerId] ?? [];
        if (empty($orphanedEntries)) {
            return false;
        }

        array_push($this->expiredUnassignedEntries, ...$orphanedEntries);

        return true;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Queries for all expired entries and populates the internal reviewer
     * and unassigned entry arrays.
     *
     * @return void
     */
    protected function setExpiredEntries(): void
    {
        $this->expiredEntriesByReviewerId = [];
        $this->expiredUnassignedEntries = [];

        foreach (PluginQuery::expiredVerifiableEntries()->all() as $record) {
            $entry = ExpiredEntryData::fromArray($record);

            if ($entry->reviewerId === null) {
                $this->expiredUnassignedEntries[] = $entry;
                continue;
            }

            $this->expiredEntriesByReviewerId[$entry->reviewerId][] = $entry;
        }
    }

    /**
     * Factory method for testing.
     *
     * @param array $expiredEntries
     * @param NotifiableInterface $recipient
     * @return ExpiredNotification
     */
    protected function buildExpiredNotification(array $expiredEntries, NotifiableInterface $recipient): ExpiredNotification
    {
        return new ExpiredNotification($expiredEntries, $recipient);
    }
}
