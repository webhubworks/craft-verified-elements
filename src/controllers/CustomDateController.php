<?php

/** @noinspection PhpUnused */

namespace webhubworks\verifiedelements\controllers;

use Craft;
use craft\web\Controller;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\Plugin;
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
            ->action(Plugin::HANDLE . '/custom-date/resolve-date')
            ->contentTemplate(Plugin::HANDLE . '/_modals/_date.twig');
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

        if (!$date) {
            return $this->asFailure(
                Craft::t(Plugin::HANDLE, 'No date provided.'),
                [
                    'errors' => [
                        'verifiedUntilDate' => [
                            Craft::t('app', '{attribute} cannot be blank.', ['attribute' => 'Date']),
                        ],
                    ],
                ]
            );
        }

        return $this->asJson([
            'date' => $date->format('Y-m-d'),
            'label' => Craft::$app->getFormatter()->asDate($date),
        ]);
    }
}
