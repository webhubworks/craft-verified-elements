<?php

namespace webhubworks\verifiedelements\services\singletons;

use craft\db\Query;
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
 *
 * @property-read int[] $enabledSectionIds
 */
class PluginSettings extends Component
{
    private ?array $_enabledSectionIds = null;

    /**
     * Previously-queried default settings for a section per site.
     *
     * @var SectionDefaults[]|null[]
     * @see getDefaultSettingsForSection
     */
    private array $sectionDefaults = [];

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
                (new Query())
                    ->select(['sectionId'])
                    ->from(PluginTable::SECTIONS)
                    ->where(['enabled' => true])
                    ->distinct()
                    ->column()
            );
        }

        return $this->_enabledSectionIds;
    }

    /**
     * Returns an array of IDs for sections (channels, structures, singles) that have been
     * enabled in this plugin's settings, filtered by a specific site.
     *
     * @param int $siteId
     * @return array
     */
    public function getEnabledSectionIdsForSite(int $siteId): array
    {
        return array_map(
            'intval',
            (new Query())
                ->select(['sectionId'])
                ->from(PluginTable::SECTIONS)
                ->where(['enabled' => true, 'siteId' => $siteId])
                ->distinct()
                ->column()
        );
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
        return (new Query())
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
                PluginTable::SECTIONS,
                compact('sectionId', 'siteId', 'reviewerId', 'enabled', 'defaultPeriod'),
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
