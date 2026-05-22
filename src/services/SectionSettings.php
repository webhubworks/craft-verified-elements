<?php

namespace webhubworks\verifiedentries\services;

use craft\db\Query;
use craft\elements\User;
use craft\helpers\Db;
use webhubworks\verifiedentries\db\Queries;
use webhubworks\verifiedentries\db\Table;
use yii\base\Component;
use yii\db\Exception;

/**
 * The SectionSettings service represents logic related to Craft Sections (channels, structures, singles) that are
 * enabled for this plugin.
 *
 * @property-read array $enabledSections
 * @property-read array $allSectionsWithSettings
 */
class SectionSettings extends Component
{
    /**
     * Returns the sections (channels, structures, singles) to list on the plugin's Settings page.
     *
     * @return array
     */
    public function getAllSectionsWithSettings(): array
    {
        $rows = Queries::sectionsWithSettings()->all();

        // Collect all unique reviewer IDs
        $reviewerIds = array_unique(array_filter(array_column($rows, 'reviewerId')));

        // Fetch User models in one go
        $reviewers = [];
        if (!empty($reviewerIds)) {
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
     * @param array $settings
     * @return void
     * @throws Exception
     */
    public function saveSectionSettings(int $sectionId, array $settings): void
    {
        $enabled = !empty($settings['enabled']);
        $defaultPeriod = $settings['defaultPeriod'] ?? null;

        $reviewerId = $settings['reviewerId'] ?? null;
        if (is_array($reviewerId)) {
            $reviewerId = reset($reviewerId) ?: null;
        } else {
            $reviewerId = $reviewerId ?: null;
        }

        Db::upsert(Table::SECTIONS,
            compact('sectionId', 'reviewerId', 'enabled', 'defaultPeriod'),
            compact('reviewerId', 'enabled', 'defaultPeriod'));
    }

    /**
     * Checks if a section is enabled for this plugin.
     *
     * @param int $sectionId
     * @return bool
     */
    public function getIsEnabledForSection(int $sectionId): bool
    {
        return (new Query())
            ->from(Table::SECTIONS)
            ->where(['sectionId' => $sectionId, 'enabled' => true])
            ->exists();
    }

    /**
     * Returns all sections (channels, structures, singles) enabled for this plugin.
     *
     * @return array
     */
    public function getEnabledSections(): array
    {
        return (new Query())
            ->select('sectionId')
            ->from(Table::SECTIONS)
            ->where(['enabled' => true])
            ->column();
    }

    /**
     * Returns default configuration for a specific section (channels, structures, singles) that
     * this plugin is applied to.
     *
     * @param int $sectionId
     * @return array|null
     */
    public function getDefaultSettingsForSection(int $sectionId): ?array
    {
        $result = (new Query())
            ->select(['enabled', 'reviewerId', 'defaultPeriod'])
            ->from(Table::SECTIONS)
            ->where(['sectionId' => $sectionId])
            ->one();

        if (!$result || !$result['enabled']) {
            return null;
        }

        return [
            $result['reviewerId'],
            $result['defaultPeriod'],
        ];
    }
}
