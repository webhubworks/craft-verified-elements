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
     * Resolves the user's selected date value in an entry's "Verified until" date field.
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

        return $this->asJson([
            'date' => $date->format('Y-m-d'),
            'label' => Craft::$app->getFormatter()->asDate($date),
        ]);
    }
}
