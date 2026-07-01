<?php

namespace webhubworks\verifiedelements\db;

use craft\db\Query;
use craft\helpers\Db;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\models\ExpiredEntryData;

/**
 * Class for abstracting out the many complicated database queries used throughout the plugin.
 * Queries that belong in this file are those with joins, requiring multiple tables to be queried.
 * Single-table queries can remain as they are throughout the plugin's logic.
 */
abstract class PluginQuery
{
    // REVIEWERS (USERS)
    // =============================================================================================

    /**
     * Returns a query for all sections (channels, structures, singles) assigned to a reviewer.
     *
     * @param int $userId The Reviewer
     * @param int|null $siteId
     * @return Query
     * @see \webhubworks\verifiedelements\services\singletons\Reviewers
     */
    public static function sectionsByReviewer(int $userId, ?int $siteId = null): Query
    {
        $query = (new Query())
            ->select([
                'ves.id',
                'ves.containerId',
                'ves.defaultPeriod',
                's.name',
                's.type',
                's.handle',
                'sites.name AS siteName',
            ])
            ->from(['ves' => PluginTable::CONTAINERS])
            ->innerJoin('{{%sections}} s', '[[s.id]] = [[ves.containerId]]')
            ->leftJoin('{{%sites}}', '[[sites.id]] = [[ves.siteId]]')
            ->where(['ves.enabled' => true])
            ->andWhere(['ves.reviewerId' => $userId]);

        if ($siteId !== null) {
            $query->andWhere(['ves.siteId' => $siteId]);
        }

        return $query;
    }

    /**
     * Returns a query for all entries assigned to a reviewer.
     *
     * @param int $userId The Reviewer
     * @param int|null $siteId
     * @return Query
     * @see \webhubworks\verifiedelements\services\singletons\Reviewers
     * @see \webhubworks\verifiedelements\models\ReviewerEntryData
     */
    public static function entriesByReviewer(int $userId, ?int $siteId = null): Query
    {
        $query = (new Query())
            ->select([
                'veea.id',
                'veea.elementId',
                'veea.siteId',
                'veea.reviewerId',
                'veea.verifiedUntilDate',
                'entries.sectionId AS containerId',
                'elements.type AS elementType',
                'es.title',
                'es.slug',
                'es.dateUpdated',
                'sections.name AS sectionName',
                'sections.handle AS sectionHandle',
                'sites.handle AS siteHandle',
                'sites.name AS siteName',
            ])
            ->from(['veea' => PluginTable::ATTRIBUTES])
            ->rightJoin(
                '{{%elements}}',
                '[[elements.id]] = [[veea.elementId]] AND [[elements.enabled]] = true AND [[elements.draftId]] IS NULL'
            )
            ->leftJoin(
                '{{%elements_sites}} es',
                '[[es.elementId]] = [[veea.elementId]] AND [[es.siteId]] = [[veea.siteId]]'
            )
            ->leftJoin('{{%entries}}', '[[entries.id]] = [[veea.elementId]]')
            ->leftJoin('{{%sections}}', '[[sections.id]] = [[entries.sectionId]]')
            ->leftJoin('{{%sites}}', '[[sites.id]] = [[veea.siteId]]')
            ->where(['veea.reviewerId' => $userId])
            ->andWhere('elements.canonicalId IS null');

        if ($siteId !== null) {
            $query->andWhere(['veea.siteId' => $siteId]);
        }

        return $query;
    }


    // VERIFICATION
    // =============================================================================================

    /**
     * Returns a query for a verifiable entry record.
     *
     * @param int $entryId
     * @param int $siteId
     * @return Query
     */
    public static function verifiableEntry(int $entryId, int $siteId): Query
    {
        return (new Query())
            ->from(PluginTable::ATTRIBUTES)
            ->where(['elementId' => $entryId, 'siteId' => $siteId]);
    }

    /**
     * Returns a query for all verifiable entries that have verification dates in the past.
     *
     * @return Query
     * @see ExpiredEntryData Populate this object with the results of the query
     */
    public static function expiredVerifiableEntries(): Query
    {
        $now = Db::prepareDateForDb(DateHelper::now());

        return (new Query())
            ->select([
                'veea.elementId',
                'veea.siteId',
                'veea.reviewerId',
                'veea.verifiedUntilDate',
                'entries.sectionId',
                'es.title',
                'sections.handle AS sectionHandle',
                'sites.handle AS siteHandle',
            ])
            ->from(['veea' => PluginTable::ATTRIBUTES])
            ->leftJoin('{{%elements}}', '[[elements.id]] = [[veea.elementId]]')
            ->leftJoin(
                '{{%elements_sites}} es',
                '[[es.elementId]] = [[veea.elementId]] AND [[es.siteId]] = [[veea.siteId]]'
            )
            ->leftJoin('{{%sites}}', '[[sites.id]] = [[veea.siteId]]')
            ->leftJoin('{{%entries}}', '[[entries.id]] = [[veea.elementId]]')
            ->innerJoin('{{%sections}}', '[[sections.id]] = [[entries.sectionId]]')
            ->innerJoin(
                PluginTable::CONTAINERS . ' ves',
                '[[ves.containerId]] = [[entries.sectionId]] AND [[ves.siteId]] = [[veea.siteId]] AND [[ves.enabled]] = 1'
            )
            ->where(['<', 'veea.verifiedUntilDate', $now])
            ->andWhere(['elements.enabled' => true])
            ->andWhere(['es.enabled' => true])
            ->andWhere('elements.canonicalId IS null');
    }


    // SETTINGS
    // =============================================================================================

    /**
     * Returns a query for sections (channels, structures, singles) that have settings.
     *
     * @param int $siteId
     * @return Query
     * @see \webhubworks\verifiedelements\services\singletons\PluginSettings::getAllSectionsWithSettings
     */
    public static function sectionsWithSettings(int $siteId): Query
    {
        return (new Query())
            ->select([
                's.id',
                's.uid',
                's.name',
                's.handle',
                's.type',
                'ves.reviewerId',
                'ves.enabled',
                'ves.defaultPeriod'
            ])
            ->from(['s' => '{{%sections}}'])
            ->innerJoin(
                '{{%sections_sites}} ss',
                '[[ss.sectionId]] = [[s.id]] AND [[ss.siteId]] = :siteId',
                [':siteId' => $siteId]
            )
            ->leftJoin(
                PluginTable::CONTAINERS . ' ves',
                '[[ves.containerId]] = [[s.id]] AND [[ves.siteId]] = :vesSiteId',
                [':vesSiteId' => $siteId]
            );
    }

    /**
     * Returns a query for a section's default settings saved on the plugin's Settings CP page.
     *
     * @param int $sectionId
     * @param int $siteId
     * @return Query
     * @see \webhubworks\verifiedelements\services\singletons\PluginSettings::getDefaultSettingsForSection()
     */
    public static function sectionDefaults(int $sectionId, int $siteId): Query
    {
        return (new Query())
            ->select([
                'ves.containerId AS id',
                's.name',
                's.handle',
                'ves.siteId',
                'ves.reviewerId',
                'ves.defaultPeriod AS period',
            ])
            ->from(['ves' => PluginTable::CONTAINERS])
            ->innerJoin('{{%sections}} s', '[[s.id]] = [[ves.containerId]]')
            ->where(['ves.containerId' => $sectionId, 'ves.siteId' => $siteId, 'ves.enabled' => true]);
    }
}
