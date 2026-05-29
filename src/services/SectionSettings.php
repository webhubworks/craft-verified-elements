<?php

namespace webhubworks\verifiedentries\services;

use craft\db\Query as CraftQuery;
use craft\elements\User;
use craft\helpers\Db;
use webhubworks\verifiedentries\db\PluginQuery;
use webhubworks\verifiedentries\db\PluginTable;
use yii\base\Component;
use yii\db\Exception;

/**
 * The SectionSettings service represents logic related to Craft Sections (channels, structures, singles) that are
 * enabled for this plugin.
 *
 * @property-read int[] $enabledSectionIds
 */
class SectionSettings extends Component
{
    private ?array $_enabledSectionIds = null;

    /**
     * Returns an array of IDs for sections (channels, structures, singles) that have been
     * enabled in this plugin's settings.
     *
     * The returned array gets memorized, so you can call this as many times as you want in a
     * request and it won't re-query the database.
     *
     * @return int[]
     */
    public function getEnabledSectionIds(): array
    {
        if ($this->_enabledSectionIds === null) {
            $this->_enabledSectionIds = array_map(
                'intval',
                (new CraftQuery())
                    ->select(['sectionId'])
                    ->from(PluginTable::SECTIONS)
                    ->where(['enabled' => true])
                    ->distinct()
                    ->column()
            );
        }

        return $this->_enabledSectionIds;
    }

    public function getEnabledSectionIdsForSite(int $siteId): array
    {
        return array_map(
            'intval',
            (new CraftQuery())
                ->select(['sectionId'])
                ->from(PluginTable::SECTIONS)
                ->where(['enabled' => true, 'siteId' => $siteId])
                ->distinct()
                ->column()
        );
    }

    /**
     * Checks if an entry's section has been enabled in this plugin's settings.
     *
     * @param int|string|null $sectionId
     * @return bool
     */
    public function isSectionEnabled(int|string|null $sectionId): bool
    {
        // Matrix entries aren't applicable to this plugin.
        if ($sectionId === null) {
            return false;
        }

        if (is_string($sectionId)) {
            $sectionId = (int)$sectionId;
        }

        // Only entries from sections that are enabled by the plugin should get the behavior.
        if (! in_array($sectionId, $this->getEnabledSectionIds(), true)) {
            return false;
        }

        return true;
    }

    /**
     * Checks if a section is enabled for this plugin.
     *
     * @param int $sectionId
     * @param int $siteId
     * @return bool
     */
    public function isSectionEnabledForSite(int $sectionId, int $siteId): bool
    {
        return (new CraftQuery())
            ->from(PluginTable::SECTIONS)
            ->where(['sectionId' => $sectionId, 'siteId' => $siteId, 'enabled' => true])
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
     * @return void
     * @throws Exception
     */
    public function saveSectionSettings(int $sectionId, int $siteId, array $settings): void
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

        Db::upsert(PluginTable::SECTIONS,
            compact('sectionId', 'siteId', 'reviewerId', 'enabled', 'defaultPeriod'),
            compact('reviewerId', 'enabled', 'defaultPeriod'));
    }

    /**
     * Returns default configuration for a specific section (channels, structures, singles) that
     * this plugin is applied to.
     *
     * @param int $sectionId
     * @param int $siteId
     * @return array|null
     */
    public function getDefaultSettingsForSection(int $sectionId, int $siteId): ?array
    {
        $result = (new CraftQuery())
            ->select(['enabled', 'reviewerId', 'defaultPeriod'])
            ->from(PluginTable::SECTIONS)
            ->where(['sectionId' => $sectionId, 'siteId' => $siteId])
            ->one();

        if (! $result || ! $result['enabled']) {
            return null;
        }

        return [
            $result['reviewerId'],
            $result['defaultPeriod'],
        ];
    }
}
