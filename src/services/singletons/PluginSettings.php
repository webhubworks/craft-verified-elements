<?php

namespace webhubworks\verifiedelements\services\singletons;

use Craft;
use craft\db\Query;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\errors\SiteNotFoundException;
use craft\helpers\Db;
use craft\models\Site;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\db\PluginTable;
use webhubworks\verifiedelements\enums\Feature;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\models\ContainerDefaults;
use yii\base\Component;
use yii\db\Exception;

/**
 * Singleton to handle related plugin settings' actions.
 *
 * @property-read int[] $inScopeSiteIds
 * @property-read array $allVolumesWithSettings
 */
class PluginSettings extends Component
{
    /**
     * Previously-queried enabled container IDs, keyed by element type + site.
     *
     * @var array<string, int[]>
     * @see getEnabledContainerIds
     */
    private array $_enabledContainerIds = [];

    /**
     * Previously-queried default settings for a container per site.
     *
     * @var ContainerDefaults[]|null[]
     * @see getDefaultSettingsForContainer
     */
    private array $containerDefaults = [];

    /**
     * Returns the IDs of the sites this plugin may operate on, honoring the edition.
     *
     * Multi-site is a Pro feature. On editions without it, the plugin is confined to the
     * primary site: non-primary sites are hidden from the UI and excluded from reads and
     * writes, even on a multi-site Craft install.
     *
     * @return int[]
     * @throws SiteNotFoundException
     */
    public function getInScopeSiteIds(): array
    {
        if (Feature::MultiSite->isEnabled()) {
            return array_map(
                static fn(Site $site): int => $site->id,
                Craft::$app->getSites()->getAllSites()
            );
        }

        return [Craft::$app->getSites()->getPrimarySite()->id];
    }

    /**
     * Whether this plugin may operate on the given site under the current edition.
     *
     * @param int $siteId
     * @return bool
     * @throws SiteNotFoundException
     */
    public function isSiteInScope(int $siteId): bool
    {
        return in_array($siteId, $this->getInScopeSiteIds(), true);
    }

    /**
     * Returns the IDs of containers (sections, volumes, etc.) that have been enabled in this
     * plugin's settings, optionally scoped to one site.
     *
     * Results are memoized per (element type, site) combination, so repeated calls in a request
     * don't re-query the database.
     *
     * @param string $elementType
     * @param int|int[]|null $siteId Limits the check to one site or a set of sites; null checks across all sites.
     * @return int[]
     */
    public function getEnabledContainerIds(string $elementType, int|array|null $siteId = null): array
    {
        $siteKey = is_array($siteId) ? implode(',', $siteId) : ($siteId ?? 'all');
        $key = $elementType . ':' . $siteKey;

        if (array_key_exists($key, $this->_enabledContainerIds)) {
            return $this->_enabledContainerIds[$key];
        }

        $query = (new Query())
            ->select(['containerId'])
            ->from(PluginTable::CONTAINERS)
            ->where(['enabled' => true, 'elementType' => $elementType])
            ->distinct();

        if ($siteId !== null) {
            $query->andWhere(['siteId' => $siteId]);
        }

        return $this->_enabledContainerIds[$key] = array_map('intval', $query->column());
    }

    /**
     * Returns the default configuration for a specific container (section, volume, etc.) that
     * this plugin is applied to.
     *
     * @param int $containerId
     * @param int $siteId
     * @param string $elementType
     * @return ContainerDefaults|null
     */
    public function getDefaultSettingsForContainer(int $containerId, int $siteId, string $elementType): ?ContainerDefaults
    {
        $key = ContainerDefaults::key($containerId, $siteId, $elementType);

        if (array_key_exists($key, $this->containerDefaults)) {
            return $this->containerDefaults[$key];
        }

        $defaults = PluginQuery::containerDefaults(
            $containerId,
            $siteId,
            $elementType
        )->one();

        if (! $defaults) {
            return $this->containerDefaults[$key] = null;
        }

        return $this->containerDefaults[$key] = new ContainerDefaults(
            id: $defaults['id'],
            name: $defaults['name'],
            handle: $defaults['handle'],
            siteId: $defaults['siteId'],
            reviewerId: $defaults['reviewerId'],
            period: $defaults['period'],
        );
    }

    /**
     * Checks if a container (section/group/volume/etc) is enabled for this plugin.
     *
     * @param int $containerId The ID for the grouping of elements (section, group, volume, etc.)
     * @param int $siteId
     * @param string $elementType
     * @return bool
     */
    public function isContainerEnabledForSite(int $containerId, int $siteId, string $elementType): bool
    {
        return (new Query())
            ->from(PluginTable::CONTAINERS)
            ->where([
                'containerId' => $containerId,
                'siteId' => $siteId,
                'elementType' => $elementType,
                'enabled' => true,
            ])
            ->exists();
    }

    /**
     * Returns the sections (channels, structures, singles) to list on the plugin's Settings page.
     *
     * @param int $siteId
     * @return array
     */
    public function getAllSectionsWithSettings(int $siteId): array
    {
        $rows = PluginQuery::sectionsWithSettings($siteId)->all();

        return $this->hydrateReviewers($rows);
    }

    /**
     * Returns the volumes to list on the plugin's Settings page.
     *
     * Volumes have no site dimension in Craft, so the primary site's rows represent all sites
     * (the save path writes identical rows for every site).
     *
     * @return array
     */
    public function getAllVolumesWithSettings(): array
    {
        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;
        $rows = PluginQuery::volumesWithSettings($primarySiteId)->all();

        return $this->hydrateReviewers($rows);
    }

    /**
     * Saves the configuration for a section (channels, structures, singles) on the plugin's
     * Settings page.
     *
     * @param int $sectionId
     * @param int $siteId
     * @param array $settings
     * @return bool If the save was successful.
     */
    public function saveSectionSettings(int $sectionId, int $siteId, array $settings): bool
    {
        return $this->upsertContainerSettings($sectionId, $siteId, Entry::class, $settings);
    }

    /**
     * Saves the configuration for a volume on the plugin's Settings page.
     *
     * Volumes have no site dimension in Craft, so the single set of settings fans out to a row
     * per site - the per-(container, site) lookups the rest of the plugin runs stay valid.
     *
     * @param int $volumeId
     * @param array $settings
     * @return bool If all sites' rows were saved successfully.
     */
    public function saveVolumeSettings(int $volumeId, array $settings): bool
    {
        $errors = 0;

        foreach (Craft::$app->getSites()->getAllSites() as $site) {
            if (! $this->upsertContainerSettings($volumeId, $site->id, Asset::class, $settings)) {
                $errors++;
            }
        }

        return $errors === 0;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Replaces each row's reviewerId with the actual User model under a 'reviewer' key, fetching
     * all reviewers in one query.
     *
     * @param array $rows
     * @return array
     */
    private function hydrateReviewers(array $rows): array
    {
        $reviewerIds = array_unique(array_filter(array_column($rows, 'reviewerId')));

        $reviewers = [];
        if (! empty($reviewerIds)) {
            $reviewerElements = User::find()
                ->id($reviewerIds)
                ->status(null)
                ->all();

            foreach ($reviewerElements as $user) {
                $reviewers[$user->id] = $user;
            }
        }

        foreach ($rows as &$row) {
            $row['reviewer'] = $reviewers[$row['reviewerId']] ?? null;
        }

        return $rows;
    }

    /**
     * Normalizes submitted settings and upserts one container's row for one site.
     *
     * @param int $containerId
     * @param int $siteId
     * @param string $elementType
     * @param array $settings
     * @return bool If the save was successful.
     */
    private function upsertContainerSettings(int $containerId, int $siteId, string $elementType, array $settings): bool
    {
        $enabled = ! empty($settings['enabled']);
        $defaultPeriod = $settings['defaultPeriod'] ?? null;

        $reviewerId = $settings['reviewerId'] ?? null;
        if (is_array($reviewerId)) {
            $reviewerId = reset($reviewerId) ?: null;
        }
        else {
            $reviewerId = $reviewerId ?: null;
        }

        try {
            Db::upsert(
                PluginTable::CONTAINERS,
                [
                    'containerId' => $containerId,
                    'elementType' => $elementType,
                    'siteId' => $siteId,
                    'reviewerId' => $reviewerId,
                    'enabled' => $enabled,
                    'defaultPeriod' => $defaultPeriod,
                ],
                compact('reviewerId', 'enabled', 'defaultPeriod')
            );
        }
        catch (Exception $exception) {
            Log::error(sprintf(
                'Failed to save container settings for %s [%s] on site %s',
                Log::element($elementType),
                $containerId,
                $siteId
            ), $exception);
            return false;
        }

        return true;
    }
}
