<?php

namespace webhubworks\verifiedentries\controllers;

use Craft;
use craft\web\Controller;
use webhubworks\verifiedentries\enums\Permission;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class SectionSettingsController extends Controller
{
    /**
     * TODO decide if we want sections tabbed by sites (as this action controls).
     *
     * @return Response
     * @throws \craft\errors\SiteNotFoundException
     */
    public function actionIndex(): Response
    {
        $sites = Craft::$app->getSites()->getAllSites();

        $siteHandle = $this->request->getQueryParam('site');
        $currentSite = $siteHandle
            ? Craft::$app->getSites()->getSiteByHandle($siteHandle)
            : Craft::$app->getSites()->getPrimarySite();

        $sections = VerifiedEntries::getInstance()
            ->getSectionSettings()
            ->getAllSectionsWithSettings($currentSite->id);

        return $this->renderTemplate(
            VerifiedEntries::HANDLE . '/_settings.twig',
            [
                'sites' => $sites,
                'currentSite' => $currentSite,
                'sections' => $sections,
                'defaultPeriodOptions' => VerifiedEntries::getInstance()->getVerification()->getPeriodOptions(),
            ]
        );
    }

    /**
     * TODO decide if we want sections grouped by sections (as this action controls).
     *
     * @return Response
     */
    public function actionGrouped(): Response
    {
        $sites = Craft::$app->getSites()->getAllSites();
        $service = VerifiedEntries::getInstance()->getSectionSettings();

        $sectionsMap = [];
        foreach ($sites as $site) {
            foreach ($service->getAllSectionsWithSettings($site->id) as $section) {
                if (!isset($sectionsMap[$section['id']])) {
                    $sectionsMap[$section['id']] = [
                        'id' => $section['id'],
                        'uid' => $section['uid'],
                        'name' => $section['name'],
                        'handle' => $section['handle'],
                        'type' => $section['type'],
                        'sites' => [],
                    ];
                }
                $sectionsMap[$section['id']]['sites'][] = [
                    'siteId' => $site->id,
                    'siteName' => $site->name,
                    'siteHandle' => $site->handle,
                    'reviewerId' => $section['reviewerId'],
                    'reviewer' => $section['reviewer'],
                    'enabled' => $section['enabled'],
                    'defaultPeriod' => $section['defaultPeriod'],
                ];
            }
        }

        return $this->renderTemplate(
            VerifiedEntries::HANDLE . '/_settings-grouped.twig',
            [
                'sections' => array_values($sectionsMap),
                'defaultPeriodOptions' => VerifiedEntries::getInstance()->getVerification()->getPeriodOptions(),
                'verifyEntriesPermission' => Permission::VerifyEntries->value,
            ]
        );
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $siteId = (int) $this->request->getRequiredBodyParam('siteId');
        $sections = $this->request->getRequiredBodyParam('sections');
        $service = VerifiedEntries::getInstance()->getSectionSettings();

        foreach ($sections as $sectionId => $settings) {
            $service->saveSectionSettings((int) $sectionId, $siteId, $settings);
        }

        $this->setSuccessFlash(Craft::t(VerifiedEntries::HANDLE, 'Verification settings saved.'));
        return $this->asSuccess();
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSaveGrouped(): Response
    {
        $this->requirePostRequest();

        $sections = $this->request->getRequiredBodyParam('sections');
        $service = VerifiedEntries::getInstance()->getSectionSettings();

        foreach ($sections as $sectionId => $siteSettings) {
            foreach ($siteSettings as $siteId => $settings) {
                $service->saveSectionSettings((int) $sectionId, (int) $siteId, $settings);
            }
        }

        $this->setSuccessFlash(Craft::t(VerifiedEntries::HANDLE, 'Verification settings saved.'));
        return $this->asSuccess();
    }
}
