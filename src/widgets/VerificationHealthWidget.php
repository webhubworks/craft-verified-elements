<?php

namespace webhubworks\verifiedelements\widgets;

use Craft;
use craft\base\Element;
use craft\base\Widget;
use craft\elements\db\AssetQuery;
use craft\elements\db\ElementQuery;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\helpers\Cp;
use Throwable;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\Plugin;

/**
 * Craft dashboard widget that shows system-wide verification health as one meter across all
 * supported element types.
 *
 * The optional site setting defines the boundary of "the system" (sites can be entirely
 * different products); there is deliberately NO element-type filter. Filtering health by element
 * type skews the overview, and per-type views already live in the plugin's dashboard views.
 *
 * @property-read null|string $bodyHtml
 * @property-read null|string $settingsHtml
 */
class VerificationHealthWidget extends Widget
{
    public const NAME = 'Verification Health';

    public ?int $siteId = null;

    /** @inheritDoc */
    public static function displayName(): string
    {
        return Craft::t(Plugin::HANDLE, self::NAME);
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
        $totalCount = 0;
        $verifiedCount = 0;
        $expiredCount = 0;

        foreach (ElementType::enabledTypes() as $elementTypeName) {
            $elementType = ElementType::from($elementTypeName);

            $totalCount += $this->liveElementsQuery($elementType)->count();
            $verifiedCount += $this->countByVerificationState($elementType, isVerified: true);
            $expiredCount += $this->countByVerificationState($elementType, isVerified: false);
        }

        $statuses = [
            [
                'label' => VerificationStatus::Verified->label(),
                'count' => $verifiedCount,
                'icon' => Cp::statusIndicatorHtml(
                    VerificationStatus::Verified->handle(),
                    ['color' => VerificationStatus::Verified->color()]
                ),
            ],
            [
                'label' => VerificationStatus::Expired->label(),
                'count' => $expiredCount,
                'icon' => Cp::statusIndicatorHtml(
                    VerificationStatus::Expired->handle(),
                    ['color' => VerificationStatus::Expired->color()]
                ),
            ],
        ];

        $siteDisplayed = null;
        if (Craft::$app->getIsMultiSite()) {
            $effectiveSiteIds = $this->effectiveSiteIds();

            if (count($effectiveSiteIds) === 1) {
                $siteDisplayed = Craft::$app->getSites()->getSiteById($effectiveSiteIds[0])?->getName();
            }
            else {
                $siteDisplayed = Craft::t('app', 'All Sites');
            }
        }

        $templateVariables = [
            'siteDisplayed' => $siteDisplayed,
            'totalCount' => $totalCount,
            'verifiedCount' => $verifiedCount,
            'expiredCount' => $expiredCount,
            'statuses' => $statuses,
            'statusColors' => [
                'verified' => VerificationStatus::Verified->cssColor(),
                'expired' => VerificationStatus::Expired->cssColor(),
            ],
        ];

        try {
            return Craft::$app->getView()->renderTemplate(
                Plugin::HANDLE . '/_widgets/verification-health.twig',
                $templateVariables
            );
        }
        catch (Throwable $exception) {
            Log::error(
                sprintf('Error loading "%s" widget', self::NAME),
                $exception
            );
        }

        return null;
    }

    /** @inheritDoc */
    public function getSettingsHtml(): ?string
    {
        if (! Craft::$app->getIsMultiSite()) {
            return null;
        }

        $inScopeSiteIds = Plugin::getInstance()->getPluginSettings()->getInScopeSiteIds();

        $options = [['label' => Craft::t('app', 'All Sites'), 'value' => '']];
        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if (in_array($site->id, $inScopeSiteIds, true)) {
                $options[] = ['label' => $site->name, 'value' => $site->id];
            }
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
            Log::error(
                sprintf('Error loading "%s" widget settings', self::NAME),
                $exception
            );
        }

        return null;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * The sites this widget counts: the configured site when it is in scope, otherwise every
     * in-scope site (all sites on multi-site editions; the primary site alone without).
     *
     * @return int[]
     */
    private function effectiveSiteIds(): array
    {
        $inScopeSiteIds = Plugin::getInstance()->getPluginSettings()->getInScopeSiteIds();

        if ($this->siteId && in_array($this->siteId, $inScopeSiteIds, true)) {
            return [$this->siteId];
        }

        return $inScopeSiteIds;
    }

    /**
     * Base query for the elements the widget counts: one row per (element, site) verification
     * unit, matching how verification state is stored.
     *
     * @param ElementType $elementType
     * @return ElementQuery
     */
    private function liveElementsQuery(ElementType $elementType): ElementQuery
    {
        /** @var class-string<Element> $elementClass */
        $elementClass = $elementType->value;

        $query = $elementClass::find()->siteId($this->effectiveSiteIds());

        // "Live" differs per type: entries respect post/expiry dates; assets are simply enabled.
        return match ($elementType) {
            ElementType::Entry => $query->status(Entry::STATUS_LIVE),
            ElementType::Asset => $query->status(Element::STATUS_ENABLED),
        };
    }

    /**
     * Counts one type's elements in enabled containers by verification state. Untracked elements
     * count as verified via the query behavior's null-date rule ("Indefinitely").
     *
     * @param ElementType $elementType
     * @param bool $isVerified
     * @return int
     * @noinspection PhpUndefinedMethodInspection
     */
    private function countByVerificationState(ElementType $elementType, bool $isVerified): int
    {
        $enabledContainerIds = Plugin::getInstance()
            ->getPluginSettings()
            ->getEnabledContainerIds($elementType->value, $this->effectiveSiteIds());

        // An empty id list must mean zero, not "unfiltered" - Craft ignores empty query params.
        if (empty($enabledContainerIds)) {
            return 0;
        }

        /** @var EntryQuery|AssetQuery $query */
        $query = $this->liveElementsQuery($elementType);

        match ($elementType) {
            ElementType::Entry => $query->sectionId($enabledContainerIds),
            ElementType::Asset => $query->volumeId($enabledContainerIds),
        };

        return (int)$query->isVerified($isVerified)->count();
    }
}
