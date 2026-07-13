<?php

namespace webhubworks\verifiedelements\console\controllers;

use craft\console\Controller;
use craft\log\Dispatcher;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\models\ElementData;
use webhubworks\verifiedelements\models\SystemRecipient;
use webhubworks\verifiedelements\models\UserRecipient;
use webhubworks\verifiedelements\Plugin;
use webhubworks\verifiedelements\services\ExpiredVerificationNotifier;
use yii\console\ExitCode;
use yii\helpers\BaseConsole;

/**
 * Check for expired elements and notify their assigned reviewers.
 */
class CheckExpiredVerificationsController extends Controller
{
    private ?ExpiredVerificationNotifier $service = null;

    /** @inheritDoc */
    public function beforeAction($action): bool
    {
        $this->service = new ExpiredVerificationNotifier(
            Dispatcher::TARGET_CONSOLE,
            Plugin::getInstance()->getPluginSettings()->getInScopeSiteIds()
        );
        return parent::beforeAction($action);
    }

    /**
     * Reports on all elements whose "Verified until" date is in the past and notifies reviewers
     * or the system's default email (for unassigned elements) about what needs to be reviewed.
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
     * Queries for all assigned elements whose "Verified until" date is in the past, groups them by the
     * Craft User assigned to review them, and sends each reviewer a digest of those elements,
     * prompting them to review the expired elements.
     *
     * @return int
     */
    public function actionReviewer(): int
    {
        $this->stdout("Checking verification dates of elements with assigned reviewers..." . PHP_EOL, BaseConsole::FG_BLUE);

        if (!$this->service->hasExpiredElementsByReviewer()) {
            $this->stdout('No expired elements.' . PHP_EOL, BaseConsole::FG_GREEN);

            return ExitCode::OK;
        }

        $this->stdout('---' . PHP_EOL);

        foreach ($this->service->getExpiredElementsByReviewer() as $reviewerId => $expiredElements) {

            // 1. Find the Reviewer
            $reviewer = $this->service->getReviewer($reviewerId);
            if (!$reviewer) {
                $this->stdout(sprintf(
                        "User %s not found or inactive. Skipping.",
                        $reviewerId
                    ) . PHP_EOL, BaseConsole::FG_RED);
                $this->service->reassignElementsToUnassigned($reviewerId);
                continue;
            }

            $this->stdout(sprintf(
                    'User %s "%s" has %s expired elements to review:',
                    $reviewer->id,
                    $reviewer->name,
                    count($expiredElements)
                ) . PHP_EOL);

            $this->listElementsInConsole($expiredElements);


            // 2. Notify the Reviewer
            $this->stdout(sprintf('Notifying %s... ', $reviewer->name));

            $isSent = $this->service->notifyRecipient(
                new UserRecipient($reviewer),
                $expiredElements
            );

            if ($isSent) {
                $this->stdout('Sent', BaseConsole::FG_GREEN);
            } else {
                $this->stdout('Failed', BaseConsole::FG_RED);
            }

            $this->stdout(PHP_EOL . '---' . PHP_EOL);
        }

        return ExitCode::OK;
    }

    /**
     * Queries for all unassigned elements whose "Verified until" date is in the past and sends a
     * digest to the system's default email, prompting a review of the unassigned and expired elements.
     *
     * @return int
     */
    public function actionUnassigned(): int
    {
        $this->stdout("Checking verification dates of unassigned elements..." . PHP_EOL, BaseConsole::FG_BLUE);

        if (!$this->service->hasUnassignedExpiredElements()) {
            $this->stdout('No expired unassigned elements.' . PHP_EOL, BaseConsole::FG_GREEN);

            return ExitCode::OK;
        }

        $this->stdout('---' . PHP_EOL);
        $this->stdout(sprintf(
                'There are %s expired elements without assigned reviewers that need to be reviewed:',
                count($this->service->getUnassignedExpiredElements())
            ) . PHP_EOL);

        $this->listElementsInConsole($this->service->getUnassignedExpiredElements());


        // 1. Notify the system admin
        $this->stdout("Notifying the system's default email recipient... ");

        $isSent = $this->service->notifyRecipient(
            new SystemRecipient(),
            $this->service->getUnassignedExpiredElements()
        );

        if ($isSent) {
            $this->stdout('Sent', BaseConsole::FG_GREEN);
        } else {
            $this->stdout('Failed', BaseConsole::FG_RED);
        }

        $this->stdout(PHP_EOL . '---' . PHP_EOL);

        return ExitCode::OK;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * @param ElementData[] $elements
     * @return void
     */
    private function listElementsInConsole(array $elements): void
    {
        $index = 0;
        foreach ($elements as $elementData) {
            $this->stdout(sprintf(
                    '[%s] %s %s "%s" on site "%s" expired on %s.',
                    ++$index,
                    Log::element($elementData->type),
                    $elementData->id,
                    $elementData->title,
                    $elementData->siteHandle,
                    $elementData->verifiedUntilDate
                ) . PHP_EOL, BaseConsole::FG_YELLOW);
        }
    }
}
