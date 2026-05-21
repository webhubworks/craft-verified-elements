<?php

namespace webhubworks\verifiedentries\controllers;

use Craft;
use craft\web\Controller;
use webhubworks\verifiedentries\services\Verification;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\db\Exception;
use yii\web\BadRequestHttpException;
use yii\web\MethodNotAllowedHttpException;
use yii\web\Response;

class SectionSettingsController extends Controller
{
    public function actionIndex(): Response
    {
        $sections = VerifiedEntries::getInstance()->sectionSettings->getAllSectionsWithSettings();

        return $this->renderTemplate(VerifiedEntries::HANDLE . '/_settings.twig', [
            'sections' => $sections,
            'defaultPeriodOptions' => Verification::getDefaultOptions(),
        ]);
    }

    /**
     * @throws Exception
     * @throws BadRequestHttpException
     * @throws MethodNotAllowedHttpException
     */
    public function actionSave(): Response
    {
        $this->requirePostRequest();

        $sections = $this->request->getRequiredBodyParam('sections');
        $service = VerifiedEntries::getInstance()->sectionSettings;

        foreach ($sections as $sectionId => $settings) {
            $service->saveSectionSettings($sectionId, $settings);
        }

        $this->setSuccessFlash(Craft::t(VerifiedEntries::HANDLE, 'Verification settings saved.'));
        return $this->asSuccess();
    }
}
