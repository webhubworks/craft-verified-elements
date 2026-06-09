<?php

namespace webhubworks\verifiedentries\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use webhubworks\verifiedentries\enums\Permission;
use webhubworks\verifiedentries\services\VerificationFieldsRenderer;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * Handles saving and rendering of the plugin's settings.
 */
class SettingsController extends Controller
{
    protected array|int|bool $allowAnonymous = self::ALLOW_ANONYMOUS_NEVER;

    /** @inheritDoc */
    public function beforeAction($action): bool
    {
        $this->requireCpRequest();
        return parent::beforeAction($action);
    }

    /**
     * Renders the plugin's main settings page.
     *
     * @return Response
     * @throws SiteNotFoundException
     */
    public function actionIndex(): Response
    {
        $sites = Craft::$app->getSites()->getAllSites();

        $siteHandle = $this->request->getQueryParam('site');
        $currentSite = $siteHandle
            ? Craft::$app->getSites()->getSiteByHandle($siteHandle)
            : Craft::$app->getSites()->getPrimarySite();

        $sections = VerifiedEntries::getInstance()
            ->getPluginSettings()
            ->getAllSectionsWithSettings($currentSite->id);

        return $this->renderTemplate(
            VerifiedEntries::HANDLE . '/_settings.twig',
            [
                'sites' => $sites,
                'currentSite' => $currentSite,
                'sections' => $sections,
                'periodSelectOptions' => VerificationFieldsRenderer::periodSelectOptions(),
            ]
        );
    }

    /**
     * Saves verification settings for all sections on a given site.
     *
     * @return Response
     * @throws Exception
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $siteId = (int) $this->request->getRequiredBodyParam('siteId');
        $sections = $this->request->getRequiredBodyParam('sections');
        $service = VerifiedEntries::getInstance()->getPluginSettings();

        foreach ($sections as $sectionId => $settings) {
            $service->saveSectionSettings((int) $sectionId, $siteId, $settings);
        }

        return $this->asSuccess(Craft::t(VerifiedEntries::HANDLE, 'Verification settings saved.'));
    }
}
