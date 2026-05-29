<?php

namespace webhubworks\verifiedentries\console\controllers;

use craft\console\Controller;
use craft\elements\User;
use webhubworks\verifiedentries\models\ExpiredEntryData;
use webhubworks\verifiedentries\models\SystemRecipient;
use webhubworks\verifiedentries\models\UserRecipient;
use webhubworks\verifiedentries\services\Verification;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

/**
 * Check for expired entries and notify their reviewers.
 */
class CheckExpiredVerificationsController extends Controller
{
    private ?Verification $verification = null;

    /** @inheritDoc */
    public function beforeAction($action): bool
    {
        $this->verification = VerifiedEntries::getInstance()->getVerification();
        return parent::beforeAction($action);
    }

    /**
     * Reports on all entries whose "Verified until" date is in the past and notifies reviewers
     * or the system's default email (for unassigned entries) about what needs to be reviewed.
     *
     * @return int
     */
    public function actionIndex(): int
    {
        $this->actionReviewer();
        $this->actionUnassigned();

        return ExitCode::OK;
    }

    /**
     * Queries for all assigned entries whose "Verified until" date is in the past, groups them by the
     * Craft User assigned to review them, and sends each reviewer a digest of those entries,
     * prompting them to review the expired entries.
     *
     * @return int
     */
    public function actionReviewer(): int
    {
        $this->stdout("Checking verification dates of entries with assigned reviewers..." . PHP_EOL, BaseConsole::FG_BLUE);

        $expiredEntriesByReviewer = $this->verification->getExpiredEntriesByReviewer();
        if (count($expiredEntriesByReviewer) === 0) {
            $this->stdout('No expired entries.' . PHP_EOL, BaseConsole::FG_GREEN);

            return ExitCode::OK;
        }

        $this->stdout('---' . PHP_EOL);

        foreach ($expiredEntriesByReviewer as $reviewerId => $expiredEntries) {

            // Get the user who needs to receive the email notification.
            /** @var User $reviewer */
            $reviewer = User::find()->id($reviewerId)->status('active')->one();
            if (! $reviewer) {
                $this->stdout(sprintf(
                        "User %s not found or inactive — skipping.",
                        $reviewerId
                    ) . PHP_EOL, BaseConsole::FG_RED);
                continue;
            }

            $this->stdout(sprintf(
                    'User %s "%s" has %s expired entries to review:',
                    $reviewer->id,
                    $reviewer->name,
                    count($expiredEntries)
                ) . PHP_EOL);

            // List the Reviewer's expired entries in the console.
            $index = 0;
            foreach ($expiredEntries as $entry) {
                $this->printEntryData(++$index, $entry);
            }

            // Send the Reviewer an email about the expired entries.
            $this->stdout(sprintf('Notifying %s... ', $reviewer->name));

            $isSent = $this->verification->sendExpiredNotification(
                new UserRecipient($reviewer),
                $expiredEntries
            );

            if ($isSent) {
                $this->stdout('Sent', BaseConsole::FG_GREEN);
            }
            else {
                $this->stdout('Failed', BaseConsole::FG_RED);
            }

            $this->stdout(PHP_EOL . '---' . PHP_EOL);
        }

        return ExitCode::OK;
    }

    /**
     * Queries for all unassigned entries whose "Verified until" date is in the past and sends a
     * digest to the system's default email, prompting a review of the unassigned and expired entries.
     *
     * @return int
     */
    public function actionUnassigned(): int
    {
        $this->stdout("Checking verification dates of unassigned entries..." . PHP_EOL, BaseConsole::FG_BLUE);

        $expiredEntries = $this->verification->getUnassignedExpiredEntries();
        if (count($expiredEntries) === 0) {
            $this->stdout('No expired unassigned entries.' . PHP_EOL, BaseConsole::FG_GREEN);

            return ExitCode::OK;
        }

        $this->stdout('---' . PHP_EOL);

        $this->stdout(sprintf(
                'There are %s expired entries without assigned reviewers that need to be reviewed:',
                count($expiredEntries)
            ) . PHP_EOL);

        // List the expired entries in the console
        $index = 0;
        foreach ($expiredEntries as $entry) {
            $this->printEntryData(++$index, $entry);
        }

        // Send an email about the expired entries to the system's default email.
        $this->stdout("Notifying the system's default email recipient... ");

        $isSent = $this->verification->sendExpiredNotification(
            new SystemRecipient(),
            $expiredEntries
        );

        if ($isSent) {
            $this->stdout('Sent', BaseConsole::FG_GREEN);
        }
        else {
            $this->stdout('Failed', BaseConsole::FG_RED);
        }

        $this->stdout(PHP_EOL . '---' . PHP_EOL);

        return ExitCode::OK;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * @param int $index
     * @param ExpiredEntryData $entry
     * @return void
     */
    private function printEntryData(int $index, ExpiredEntryData $entry): void
    {
        $this->stdout(sprintf(
                '[%s] Entry %s "%s" on site "%s" expired on %s.',
                $index,
                $entry->id,
                $entry->title,
                $entry->siteHandle,
                $entry->verifiedUntilDate
            ) . PHP_EOL, BaseConsole::FG_YELLOW);
    }
}
