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
 */
class SectionSettings extends Component
{
    public function getAllSectionSettings(): array
    {
        return (new Query())
            ->from(Table::SECTIONS)
            ->indexBy('sectionId')
            ->all();
    }

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

        Db::upsert(Table::SECTIONS, [
            'sectionId' => $sectionId,
            'reviewerId' => $reviewerId,
            'enabled' => $enabled,
            'defaultPeriod' => $defaultPeriod,
        ], [
            'reviewerId' => $reviewerId,
            'enabled' => $enabled,
            'defaultPeriod' => $defaultPeriod,
        ]);
    }

    public function getIsEnabledForSection(int $sectionId): bool
    {
        return (new Query())
            ->from(Table::SECTIONS)
            ->where(['sectionId' => $sectionId, 'enabled' => true])
            ->exists();
    }

    public function getEnabledSections(): array
    {
        return (new Query())
            ->select('sectionId')
            ->from(Table::SECTIONS)
            ->where(['enabled' => true])
            ->column();
    }

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
