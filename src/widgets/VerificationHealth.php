<?php

namespace webhubworks\verifiedelements\widgets;

use Craft;
use craft\base\Widget;
use craft\elements\Entry;
use craft\helpers\Cp;
use Throwable;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\Plugin;

/**
 * Verification Health widget type
 *
 * @property-read null|string $bodyHtml
 * @property-read null|string $settingsHtml
 */
class VerificationHealth extends Widget
{
    public ?int $siteId = null;

    /** @inheritDoc */
    public static function displayName(): string
    {
        return Craft::t(Plugin::HANDLE, 'Verification Health');
    }

    /** @inheritDoc */
    public static function isSelectable(): bool
    {
        return true;
    }

    /** @inheritDoc */
    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public static function icon(): ?string
    {
        return 'heart';
    }

    /**
     * @inheritDoc
     * @noinspection PhpUndefinedMethodInspection
     */
    public function getBodyHtml(): ?string
    {
        $settings = Plugin::getInstance()->getPluginSettings();
        $enabledSectionIds = $this->siteId
            ? $settings->getEnabledSectionIdsForSite($this->siteId)
            : $settings->getEnabledSectionIds();

        $site = $this->siteId ?: '*';

        $totalEntryCount = Entry::find()
            ->status(Entry::STATUS_LIVE)
            ->siteId($site)
            ->section('*')
            ->count();

        $verifiedEntryCount = Entry::find()
            ->status(Entry::STATUS_LIVE)
            ->siteId($site)
            ->sectionId($enabledSectionIds)
            ->isVerified(true)
            ->count();

        $expiredEntryCount = Entry::find()
            ->status(Entry::STATUS_LIVE)
            ->siteId($site)
            ->sectionId($enabledSectionIds)
            ->isVerified(false)
            ->count();

        $statuses = [
            [
                'label' => VerificationStatus::Verified->label(),
                'count' => $verifiedEntryCount,
                'icon' => Cp::statusIndicatorHtml(
                    VerificationStatus::Verified->handle(),
                    ['color' => VerificationStatus::Verified->color()]
                ),
            ],
            [
                'label' => VerificationStatus::Expired->label(),
                'count' => $expiredEntryCount,
                'icon' => Cp::statusIndicatorHtml(
                    VerificationStatus::Expired->handle(),
                    ['color' => VerificationStatus::Expired->color()]
                ),
            ],
        ];

        $siteDisplayed = null;
        if (Craft::$app->getIsMultiSite()) {
            $siteDisplayed = $this->siteId
                ? Craft::$app->getSites()->getSiteById($this->siteId)?->getName()
                : Craft::t('app', 'All Sites');
        }

        $templateVariables = [
            'siteDisplayed' => $siteDisplayed,
            'totalCount' => $totalEntryCount,
            'verifiedCount' => $verifiedEntryCount,
            'expiredCount' => $expiredEntryCount,
            'statuses' => $statuses,
            'statusColors' => [
                'verified' => VerificationStatus::Verified->cssColor(),
                'expired' => VerificationStatus::Expired->cssColor(),
            ],
        ];

        try {
            return Craft::$app->getView()->renderTemplate(
                Plugin::HANDLE . '/_widgets/health.twig',
                $templateVariables
            );
        }
        catch (Throwable $exception) {
            Log::error('Error loading "Verification Health" widget', $exception);
        }

        return null;
    }

    /** @inheritDoc */
    public function getSettingsHtml(): ?string
    {
        if (! Craft::$app->getIsMultiSite()) {
            return null;
        }

        $options = [['label' => Craft::t('app', 'All Sites'), 'value' => '']];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            $options[] = ['label' => $site->name, 'value' => $site->id];
        }

        $templateVariables = [
            'label' => Craft::t(Plugin::HANDLE, 'Site'),
            'name' => 'siteId',
            'options' => $options,
            'value' => $this->siteId ? (string)$this->siteId : '',
        ];

        try {
            return Craft::$app->getView()->renderTemplate(
                '_includes/forms/select.twig',
                $templateVariables
            );
        }
        catch (Throwable $exception) {
            Log::error('Error loading "Verification Health" widget settings', $exception);
        }

        return null;
    }
}
