<?php /** @noinspection PhpUnused */

namespace webhubworks\verifiedelements\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\web\Controller;
use webhubworks\verifiedelements\enums\Feature;
use webhubworks\verifiedelements\services\VerificationFieldsRenderer;
use webhubworks\verifiedelements\Plugin;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

/**
 * Handles saving and rendering of the plugin's settings subpages (one per element type, plus
 * the subscription plan page).
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
     * Renders the settings page for entries (per-section, per-site settings).
     *
     * @return Response
     * @throws SiteNotFoundException
     */
    public function actionEntries(): Response
    {
        $sites = Craft::$app->getSites()->getAllSites();

        $siteHandle = $this->request->getQueryParam('site');
        $currentSite = $siteHandle
            ? Craft::$app->getSites()->getSiteByHandle($siteHandle)
            : Craft::$app->getSites()->getPrimarySite();

        $sections = Plugin::getInstance()
            ->getPluginSettings()
            ->getAllSectionsWithSettings($currentSite->id);

        return $this->renderTemplate(
            Plugin::HANDLE . '/_settings/entries.twig',
            [
                ...$this->sharedTemplateVariables('entries'),
                'sites' => $sites,
                'currentSite' => $currentSite,
                'sections' => $sections,
                'periodSelectOptions' => VerificationFieldsRenderer::periodSelectOptions(),
            ]
        );
    }

    /**
     * Renders the settings page for assets (per-volume settings; volumes have no site dimension).
     *
     * @return Response
     */
    public function actionAssets(): Response
    {
        $volumes = Plugin::getInstance()
            ->getPluginSettings()
            ->getAllVolumesWithSettings();

        return $this->renderTemplate(
            Plugin::HANDLE . '/_settings/assets.twig',
            [
                ...$this->sharedTemplateVariables('assets'),
                'volumes' => $volumes,
                'periodSelectOptions' => VerificationFieldsRenderer::periodSelectOptions(),
            ]
        );
    }

    /**
     * Renders the subscription plan page, where the user can manage the plugin edition.
     *
     * @return Response
     */
    public function actionSubscriptionPlan(): Response
    {
        return $this->renderTemplate(
            Plugin::HANDLE . '/_settings/subscription-plan.twig',
            $this->sharedTemplateVariables('subscriptionPlan')
        );
    }

    /**
     * Saves verification settings for all sections on a given site.
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSaveEntries(): Response
    {
        $this->requirePostRequest();

        $siteId = (int) $this->request->getRequiredBodyParam('siteId');
        $sections = $this->request->getRequiredBodyParam('sections');
        $service = Plugin::getInstance()->getPluginSettings();

        foreach ($sections as $sectionId => $settings) {
            $service->saveSectionSettings((int) $sectionId, $siteId, $settings);
        }

        return $this->asSuccess(Craft::t(Plugin::HANDLE, 'Verification settings saved.'));
    }

    /**
     * Saves verification settings for all volumes (fanned out to every site).
     *
     * @return Response
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSaveAssets(): Response
    {
        $this->requirePostRequest();

        $volumes = $this->request->getRequiredBodyParam('volumes');
        $service = Plugin::getInstance()->getPluginSettings();

        foreach ($volumes as $volumeId => $settings) {
            $service->saveVolumeSettings((int) $volumeId, $settings);
        }

        return $this->asSuccess(Craft::t(Plugin::HANDLE, 'Verification settings saved.'));
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Returns the template variables every settings subpage needs: which sub-menu item is
     * selected and which subpages the current edition exposes.
     *
     * @param string $selectedSettingsPage
     * @return array
     */
    private function sharedTemplateVariables(string $selectedSettingsPage): array
    {
        return [
            'selectedSettingsPage' => $selectedSettingsPage,
            'showEntriesSettings' => Feature::EntryVerification->isEnabled(),
            'showAssetsSettings' => Feature::AssetVerification->isEnabled(),
        ];
    }
}
