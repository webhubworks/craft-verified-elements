<?php

namespace webhubworks\verifiedentries\services;

use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use webhubworks\verifiedentries\db\Queries;
use webhubworks\verifiedentries\db\PluginTable;
use yii\base\Component;
use yii\db\Exception;

/**
 * The SectionSettings service represents logic related to Craft Sections (channels, structures, singles) that are
 * enabled for this plugin.
 */
class SectionSettings extends Component
{
    /**
     * Returns the sections (channels, structures, singles) to list on the plugin's Settings page.
     *
     * @param int $siteId
     * @return array
     */
    public function getAllSectionsWithSettings(int $siteId): array
    {
        $rows = Queries::sectionsWithSettings($siteId)->all();

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
     * Checks if a section is enabled for this plugin.
     *
     * @param int $sectionId
     * @param int $siteId
     * @return bool
     */
    public function getIsEnabledForSection(int $sectionId, int $siteId): bool
    {
        return (new Query())
            ->from(PluginTable::SECTIONS)
            ->where(['sectionId' => $sectionId, 'siteId' => $siteId, 'enabled' => true])
            ->exists();
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
        $result = (new Query())
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
