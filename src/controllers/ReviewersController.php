<?php

namespace webhubworks\verifiedelements\controllers;

use craft\web\Controller;
use craft\controllers\EditUserTrait;
use craft\web\CpScreenResponseBehavior;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\Feature;
use webhubworks\verifiedelements\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\ForbiddenHttpException;
use yii\web\Response;

/**
 * Handles the Verified Elements tab on a reviewer's user edit screen.
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
     * Renders the reviewer's Verified Elements tab.
     *
     * @param int|null $userId
     * @return Response
     * @throws BadRequestHttpException
     * @throws ForbiddenHttpException
     */
    public function actionIndex(?int $userId = null): Response
    {
        $user = $this->editedUser($userId);

        $showEntriesTab = Feature::EntryVerification->isEnabled();
        $showAssetsTab = Feature::AssetVerification->isEnabled();

        // Default to the first available tab; ignore requests for tabs the edition disables.
        $defaultElementType = $showEntriesTab
            ? ElementType::Entry->uriSegment()
            : ElementType::Asset->uriSegment();
        $selectedElementType = $this->request->getQueryParam('elementType', $defaultElementType);

        if ($selectedElementType === ElementType::Asset->uriSegment() && ! $showAssetsTab) {
            $selectedElementType = $defaultElementType;
        }

        /** @var Response|CpScreenResponseBehavior $response */
        $response = $this->asEditUserScreen($user, Plugin::HANDLE);

        return $response->contentTemplate(
            Plugin::HANDLE . '/_user.twig',
            [
                'userId' => $user->id,
                'selectedElementType' => $selectedElementType,
                'showEntriesTab' => $showEntriesTab,
                'showAssetsTab' => $showAssetsTab,
            ]
        );
    }
}
