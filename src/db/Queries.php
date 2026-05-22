<?php

namespace webhubworks\verifiedentries\db;

use craft\db\Query;
use craft\helpers\Db;
use DateTime;

/**
 * Class for abstracting out the many complicated database queries used throughout the plugin. Queries that belong in
 * this file are those with joins, requiring multiple tables to be queried. Single-table queries can remain as they are
 * throughout the plugin's logic.
 */
abstract class Queries
{
    // REVIEWERS (USERS)
    // =================================================================================================================

    /**
     * Returns a query for all sections (channels, structures, singles) assigned to a reviewer.
     *
     * @param int $userId The Reviewer
     * @param int|null $siteId
     * @return Query
     * @see \webhubworks\verifiedentries\services\Reviewers
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
            ])
            ->from(['ves' => Table::SECTIONS])
            ->innerJoin('{{%sections}} s', '[[s.id]] = [[ves.sectionId]]')
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
     * @see \webhubworks\verifiedentries\services\Reviewers
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
            ])
            ->from(['veea' => Table::ENTRIES])
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
    // =================================================================================================================

    /**
     * Returns a query for a verifiable entry record.
     *
     * @param int $entryId
     * @param int $siteId
     * @return Query
     * @see \webhubworks\verifiedentries\services\Verification
     */
    public static function verifiableEntry(int $entryId, int $siteId): Query
    {
        return (new Query())
            ->from(Table::ENTRIES)
            ->where(['entryId' => $entryId, 'siteId' => $siteId]);
    }

    /**
     * Returns a query for all verifiable entries that have verification dates in the past.
     *
     * @return Query
     * @see \webhubworks\verifiedentries\services\Verification::checkExpiredVerifications
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
            ->from(['veea' => Table::ENTRIES])
            ->leftJoin('{{%elements}}', '[[elements.id]] = [[veea.entryId]] AND [[elements.enabled]] = true')
            ->leftJoin(
                '{{%elements_sites}} es',
                '[[es.elementId]] = [[veea.entryId]] AND [[es.siteId]] = [[veea.siteId]]'
            )
            ->leftJoin('{{%sites}}', '[[sites.id]] = [[veea.siteId]]')
            ->leftJoin('{{%entries}}', '[[entries.id]] = [[veea.entryId]]')
            ->innerJoin('{{%sections}}', '[[sections.id]] = [[entries.sectionId]]')
            ->innerJoin(
                Table::SECTIONS . ' ves',
                '[[ves.sectionId]] = [[entries.sectionId]] AND [[ves.siteId]] = [[veea.siteId]] AND [[ves.enabled]] = 1'
            )
            ->where(['<', 'veea.verifiedUntilDate', Db::prepareDateForDb(new DateTime())])
            ->andWhere('elements.canonicalId IS null');
    }


    // SETTINGS
    // =================================================================================================================

    /**
     * Returns a query for sections (channels, structures, singles) that have settings.
     *
     * @param int $siteId
     * @return Query
     * @see \webhubworks\verifiedentries\services\SectionSettings::getAllSectionsWithSettings
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
                Table::SECTIONS . ' ves',
                '[[ves.sectionId]] = [[s.id]] AND [[ves.siteId]] = :vesSiteId',
                [':vesSiteId' => $siteId]
            );
    }
}