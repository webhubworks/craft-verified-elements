<?php

namespace webhubworks\verifiedelements\services\singletons;

use craft\db\Query;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Db;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\db\PluginTable;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\models\SectionDefaults;
use yii\base\Component;
use yii\db\Exception;

/**
 * Singleton to handle related plugin settings' actions.
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
     * Previously-queried default settings for a section per site.
     *
     * @var SectionDefaults[]|null[]
     * @see getDefaultSettingsForSection
     */
    private array $sectionDefaults = [];

    /**
     * Returns the IDs of containers (sections for entries, volumes for assets, ...) that have been
     * enabled in this plugin's settings, optionally scoped to one site.
     *
     * Results are memoized per (element type, site) combination, so repeated calls in a request
     * don't re-query the database.
     *
     * @param string $elementType
     * @param int|null $siteId Limits the check to one site; null checks across all sites.
     * @return int[]
     */
    public function getEnabledContainerIds(string $elementType, ?int $siteId = null): array
    {
        $key = $elementType . ':' . ($siteId ?? 'all');

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

        // Collect all unique reviewer IDs
        $reviewerIds = array_unique(array_filter(array_column($rows, 'reviewerId')));

        // Fetch User models in one go
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

        // Replace reviewerId with actual User model (if found)
        foreach ($rows as &$row) {
            $row['reviewer'] = $reviewers[$row['reviewerId']] ?? null;
        }

        return $rows;
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
                    'containerId' => $sectionId,
                    'elementType' => Entry::class,
                    'siteId' => $siteId,
                    'reviewerId' => $reviewerId,
                    'enabled' => $enabled,
                    'defaultPeriod' => $defaultPeriod,
                ],
                compact('reviewerId', 'enabled', 'defaultPeriod')
            );
        }
        catch (Exception $exception) {
            Log::error('Failed to save section settings', $exception);
            return false;
        }

        return true;
    }

    /**
     * Returns default configuration for a specific section (channels, structures, singles) that
     * this plugin is applied to.
     *
     * @param int $sectionId
     * @param int $siteId
     * @return SectionDefaults|null
     */
    public function getDefaultSettingsForSection(int $sectionId, int $siteId): ?SectionDefaults
    {
        $key = SectionDefaults::key($sectionId, $siteId);

        if (array_key_exists($key, $this->sectionDefaults)) {
            return $this->sectionDefaults[$key];
        }

        $defaults = PluginQuery::sectionDefaults($sectionId, $siteId)->one();

        if (! $defaults) {
            return $this->sectionDefaults[$key] = null;
        }

        return $this->sectionDefaults[$key] = new SectionDefaults(
            id: $defaults['id'],
            name: $defaults['name'],
            handle: $defaults['handle'],
            siteId: $defaults['siteId'],
            reviewerId: $defaults['reviewerId'],
            period: $defaults['period'],
        );
    }
}
