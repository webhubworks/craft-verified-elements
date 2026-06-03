<?php

namespace webhubworks\verifiedentries\db;

use Carbon\Carbon;
use Craft;
use craft\db\Query;
use craft\helpers\Db;
use webhubworks\verifiedentries\models\ExpiredEntryData;

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
     * @see \webhubworks\verifiedentries\services\singletons\Reviewers
     */
    public static function sectionsByReviewer(int $userId, ?int $siteId = null): Query
    {
        $query = (new Query())
            ->select([
                'ves.id',
                'ves.sectionId',
                'ves.defaultPeriod',
                's.name',
                's.type',
                's.handle',
                'sites.name AS siteName',
            ])
            ->from(['ves' => PluginTable::SECTIONS])
            ->innerJoin('{{%sections}} s', '[[s.id]] = [[ves.sectionId]]')
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
     * @see \webhubworks\verifiedentries\services\singletons\Reviewers
     */
    public static function entriesByReviewer(int $userId, ?int $siteId = null): Query
    {
        $query = (new Query())
            ->select([
                'veea.id',
                'veea.entryId',
                'veea.siteId',
                'veea.reviewerId',
                'veea.verifiedUntilDate',
                'entries.sectionId',
                'es.title',
                'es.slug',
                'es.dateUpdated',
                'sections.name AS sectionName',
                'sections.handle AS sectionHandle',
                'sites.handle AS siteHandle',
                'sites.name AS siteName',
            ])
            ->from(['veea' => PluginTable::ENTRIES])
            ->rightJoin('{{%elements}}', '[[elements.id]] = [[veea.entryId]] AND [[elements.enabled]] = true')
            ->leftJoin(
                '{{%elements_sites}} es',
                '[[es.elementId]] = [[veea.entryId]] AND [[es.siteId]] = [[veea.siteId]]'
            )
            ->leftJoin('{{%entries}}', '[[entries.id]] = [[veea.entryId]]')
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
            ->from(PluginTable::ENTRIES)
            ->where(['entryId' => $entryId, 'siteId' => $siteId]);
    }

    /**
     * Returns a query for all verifiable entries that have verification dates in the past.
     *
     * @return Query
     * @see ExpiredEntryData Populate this object with the results of the query
     */
    public static function expiredVerifiableEntries(): Query
    {
        return (new Query())
            ->select([
                'veea.entryId',
                'veea.siteId',
                'veea.reviewerId',
                'veea.verifiedUntilDate',
                'entries.sectionId',
                'es.title',
                'sections.handle AS sectionHandle',
                'sites.handle AS siteHandle',
            ])
            ->from(['veea' => PluginTable::ENTRIES])
            ->leftJoin('{{%elements}}', '[[elements.id]] = [[veea.entryId]] AND [[elements.enabled]] = true')
            ->leftJoin(
                '{{%elements_sites}} es',
                '[[es.elementId]] = [[veea.entryId]] AND [[es.siteId]] = [[veea.siteId]]'
            )
            ->leftJoin('{{%sites}}', '[[sites.id]] = [[veea.siteId]]')
            ->leftJoin('{{%entries}}', '[[entries.id]] = [[veea.entryId]]')
            ->innerJoin('{{%sections}}', '[[sections.id]] = [[entries.sectionId]]')
            ->innerJoin(
                PluginTable::SECTIONS . ' ves',
                '[[ves.sectionId]] = [[entries.sectionId]] AND [[ves.siteId]] = [[veea.siteId]] AND [[ves.enabled]] = 1'
            )
            ->where(['<', 'veea.verifiedUntilDate', Db::prepareDateForDb(Carbon::now(Craft::$app->getTimeZone()))])
            ->andWhere('elements.canonicalId IS null');
    }


    // SETTINGS
    // =============================================================================================

    /**
     * Returns a query for sections (channels, structures, singles) that have settings.
     *
     * @param int $siteId
     * @return Query
     * @see \webhubworks\verifiedentries\services\singletons\PluginSettings::getAllSectionsWithSettings
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
                PluginTable::SECTIONS . ' ves',
                '[[ves.sectionId]] = [[s.id]] AND [[ves.siteId]] = :vesSiteId',
                [':vesSiteId' => $siteId]
            );
    }

    /**
     * Returns a query for a section's default settings saved on the plugin's Settings CP page.
     *
     * @param int $sectionId
     * @param int $siteId
     * @return Query
     * @see \webhubworks\verifiedentries\services\singletons\PluginSettings::getDefaultSettingsForSection()
     */
    public static function sectionDefaults(int $sectionId, int $siteId): Query
    {
        return (new Query())
            ->select([
                'ves.sectionId AS id',
                's.name',
                's.handle',
                'ves.siteId',
                'ves.reviewerId',
                'ves.defaultPeriod AS period',
            ])
            ->from(['ves' => PluginTable::SECTIONS])
            ->innerJoin('{{%sections}} s', '[[s.id]] = [[ves.sectionId]]')
            ->where(['ves.sectionId' => $sectionId, 'ves.siteId' => $siteId, 'ves.enabled' => true]);
    }
}
