<?php /** @noinspection PhpUnused */

namespace webhubworks\verifiedentries\controllers;

use Craft;
use craft\helpers\DateTimeHelper;
use craft\web\Controller;
use webhubworks\verifiedentries\helpers\DateHelper;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\web\Response;

/**
 * Handles the custom date modal for selecting a specific verification date on an entry's "edit"
 * page.
 */
class CustomDateController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /** @inheritDoc */
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        return parent::beforeAction($action);
    }

    /**
     * Renders the custom date modal.
     *
     * @return Response
     */
    public function actionIndex(): Response
    {
        return $this->asCpModal()
            ->action(VerifiedEntries::HANDLE . '/custom-date/resolve-date')
            ->contentTemplate(VerifiedEntries::HANDLE . '/_modals/_date.twig');
    }

    /**
     * Receives the POST from the modal, validates that the submitted date is present and in the
     * future, and returns the formatted date as JSON on success or a failure response with field
     * errors on failure.
     *
     * @return Response
     * @throws \yii\web\MethodNotAllowedHttpException
     */
    public function actionResolveDate(): Response
    {
        $this->requirePostRequest();

        $date = DateHelper::toDateTime($this->request->getBodyParam('verifiedUntilDate'));

        if (! $date) {
            return $this->asFailure(
                Craft::t(VerifiedEntries::HANDLE, 'No date provided.'),
                [
                    'errors' => [
                        'verifiedUntilDate' => [
                            Craft::t('app', '{attribute} cannot be blank.', ['attribute' => 'Date'])
                        ]
                    ]
                ]
            );
        }

        if ($date < DateTimeHelper::now()) {
            return $this->asFailure(
                Craft::t(VerifiedEntries::HANDLE, 'Could not set verification date.'),
                [
                    'errors' => [
                        'verifiedUntilDate' => [
                            Craft::t(VerifiedEntries::HANDLE, 'Date must be in the future.'),
                        ]
                    ]
                ]
            );
        }

        $formatter = Craft::$app->getFormatter();

        return $this->asJson([
            'date' => $date->format('Y-m-d'),
            'label' => $formatter->asDate($date),
        ]);
    }
}
