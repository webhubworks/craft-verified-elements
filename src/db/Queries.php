<?php

namespace webhubworks\verifiedentries\db;

use craft\db\Query;
use webhubworks\verifiedentries\services\Reviewers;

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
     * @see Reviewers
     */
    public static function sectionsByReviewer(int $userId, ?int $siteId = null): Query
    {
        return (new Query())
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
    }

    /**
     * Returns a query for all entries assigned to a reviewer.
     *
     * @param int $userId The Reviewer
     * @param int|null $siteId
     * @return Query
     * @see Reviewers
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

    //
    // =================================================================================================================


    //
    // =================================================================================================================
}