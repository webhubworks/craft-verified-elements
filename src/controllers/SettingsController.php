<?php /** @noinspection PhpUnused */

namespace webhubworks\verifiedelements\controllers;

use Craft;
use craft\errors\SiteNotFoundException;
use craft\models\Site;
use craft\web\Controller;
use webhubworks\verifiedelements\enums\Edition;
use webhubworks\verifiedelements\enums\Feature;
use webhubworks\verifiedelements\enums\Permission;
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
        $this->requirePermission(Permission::ManagePluginSettings->value);

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
        $pluginSettings = Plugin::getInstance()->getPluginSettings();
        $inScopeSiteIds = $pluginSettings->getInScopeSiteIds();

        // Only offer the sites this edition may manage. The template hides the site tabs
        // when one remains, so lite collapses to a single primary-site view.
        $sites = array_values(array_filter(
            Craft::$app->getSites()->getAllSites(),
            static fn(Site $site): bool => in_array($site->id, $inScopeSiteIds, true)
        ));

        $requestedSiteHandle = $this->request->getQueryParam('site');
        $requestedSite = $requestedSiteHandle
            ? Craft::$app->getSites()->getSiteByHandle($requestedSiteHandle)
            : null;

        // Ignore an out-of-scope ?site= and fall back to the primary site.
        $currentSite = Craft::$app->getSites()->getPrimarySite();
        if ($requestedSite && in_array($requestedSite->id, $inScopeSiteIds, true)) {
            $currentSite = $requestedSite;
        }

        $sections = $pluginSettings->getAllSectionsWithSettings($currentSite->id);

        return $this->renderTemplate(
            Plugin::HANDLE . '/_settings/entries.twig',
            [
                ...$this->sharedTemplateVariables('entries'),
                'sites' => $sites,
                'currentSite' => $currentSite,
                'sections' => $sections,
                'periodSelectOptions' => VerificationFieldsRenderer::periodSelectOptions(),
                'assignableReviewerPermission' => Permission::VerifyEntries->value,
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
                'assignableReviewerPermission' => Permission::VerifyAssets->value,
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
        $editions = array_map(
            static fn(Edition $edition): array => [
                'handle' => $edition->value,
                'name' => $edition->label(),
            ],
            Edition::currentlyAvailable()
        );

        return $this->renderTemplate(
            Plugin::HANDLE . '/_settings/subscription-plan.twig',
            [
                ...$this->sharedTemplateVariables('subscriptionPlan'),
                'editions' => $editions,
                'currentEditionHandle' => Plugin::getInstance()->edition,
            ]
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

        $siteId = (int)$this->request->getRequiredBodyParam('siteId');
        $sections = $this->request->getRequiredBodyParam('sections');
        $service = Plugin::getInstance()->getPluginSettings();

        // The UI never offers an out-of-scope site on lower editions, so a siteId outside
        // the in-scope set is a stale or forged post.
        if (! in_array($siteId, $service->getInScopeSiteIds(), true)) {
            throw new BadRequestHttpException('Site is not available on this edition.');
        }

        foreach ($sections as $sectionId => $settings) {
            $service->saveSectionSettings((int)$sectionId, $siteId, $settings);
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
            $service->saveVolumeSettings((int)$volumeId, $settings);
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
