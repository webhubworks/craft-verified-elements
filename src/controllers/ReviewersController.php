<?php

namespace webhubworks\verifiedentries\controllers;

use craft\web\Controller;
use craft\controllers\EditUserTrait;
use craft\web\CpScreenResponseBehavior;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Handles the Verified Entries tab on a reviewer's user edit screen.
 */
class ReviewersController extends Controller
{
    use EditUserTrait;

    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /** @inheritDoc */
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        return parent::beforeAction($action);
    }

    /**
     * Renders the reviewer's Verified Entries tab.
     *
     * @param int|null $userId
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionIndex(?int $userId = null): Response
    {
        $user = $this->editedUser($userId);

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, VerifiedEntries::HANDLE);

        return $response->contentTemplate(
            VerifiedEntries::HANDLE . '/_user.twig',
            [
                'sections' => VerifiedEntries::getInstance()->getReviewers()->getSections($userId),
                'userId' => $userId,
            ]
        );
    }
}
