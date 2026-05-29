<?php

namespace webhubworks\verifiedentries\console\controllers;

use craft\console\Controller;
use craft\elements\User;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

/**
 * Check for expired entries and notify their reviewers.
 */
class CheckExpiredVerificationsController extends Controller
{
    /**
     * Queries for all entries whose "Verified until" date is in the past, groups them by the
     * Craft User assigned to review them, and sends each reviewer a digest of those entries,
     * prompting them to review the expired entries.
     *
     * @return int
     */
    public function actionIndex(): int
    {
        $this->stdout("Checking verification dates of all entries in enabled sections...\n");

        $verification = VerifiedEntries::getInstance()->getVerification();
        $expiredEntriesByReviewer = $verification->getExpiredEntriesByReviewer();

        if (count($expiredEntriesByReviewer) === 0) {
            $this->stdout('No expired entries.');

            return ExitCode::OK;
        }

        $this->stdout('---' . PHP_EOL);

        foreach ($expiredEntriesByReviewer as $reviewerId => $expiredEntries) {

            // Get the user who needs to receive the email notification.
            $reviewer = User::find()->id($reviewerId)->status('active')->one();
            if (! $reviewer) {
                $this->stdout(sprintf(
                    "User %s not found or inactive — skipping.",
                    $reviewerId
                ). PHP_EOL, BaseConsole::FG_RED);
                continue;
            }

            $this->stdout(sprintf(
                    'User %s "%s" has %s expired entries to review:',
                    $reviewer->id,
                    $reviewer->name,
                    count($expiredEntries)
                ) . "\n");

            // List the Reviewer's expired entries in the console.
            $index = 0;
            foreach ($expiredEntries as $entry) {
                $this->stdout(sprintf(
                    '[%s] Entry %s "%s" on site "%s" expired on %s.',
                    ++$index,
                    $entry->id,
                    $entry->title,
                    $entry->siteHandle,
                    $entry->verifiedUntilDate
                ) . PHP_EOL, BaseConsole::FG_YELLOW);
            }

            // Send the Reviewer an email about the expired entries.
            $this->stdout(sprintf('Notifying %s... ', $reviewer->name));

            if ($verification->sendExpiredNotification($reviewer, $expiredEntries)) {
                $this->stdout('Sent', BaseConsole::FG_GREEN);
            }
            else {
                $this->stdout('Failed', BaseConsole::FG_RED);
            }

            $this->stdout(PHP_EOL . '---' . PHP_EOL);
        }

        return ExitCode::OK;
    }
}
