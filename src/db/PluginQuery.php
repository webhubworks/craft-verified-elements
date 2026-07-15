<?php

namespace webhubworks\verifiedelements\db;

use craft\db\Query;
use craft\helpers\Db;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\helpers\DateHelper;

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
     * Returns a query for all elements (of the given element types) assigned to a reviewer,
     * ready for ordering and pagination.
     *
     * The per-type queries are combined with UNION ALL and wrapped as a subquery, because Yii
     * applies `orderBy`/`limit` to the first subquery only when they're set on a query that
     * carries unions - ordering and pagination must happen outside the union.
     *
     * @param int $userId The Reviewer
     * @param string[] $elementTypes Element FQCNs (at least one), e.g. `[Entry::class, Asset::class]`
     * @param int[] $inScopeSiteIds Sites the current edition may surface (see PluginSettings::getInScopeSiteIds)
     * @param int|null $siteId Narrows to a single site within the in-scope set
     * @return Query
     * @see \webhubworks\verifiedelements\services\singletons\Reviewers
     * @see \webhubworks\verifiedelements\models\ElementData
     */
    public static function elementsByReviewer(
        int   $userId,
        array $elementTypes,
        array $inScopeSiteIds,
        ?int  $siteId = null,
    ): Query {
        $queries = array_map(
            static fn(string $elementType) => self::reviewerElements(
                ElementType::from($elementType),
                $userId,
                $siteId,
                $inScopeSiteIds
            ),
            $elementTypes
        );

        return self::unionAll($queries);
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
     * Returns a query for all verifiable elements (of the given element types) that have
     * verification dates in the past.
     *
     * @param string[] $elementTypes Element FQCNs (at least one), e.g. `[Entry::class, Asset::class]`
     * @param int[] $inScopeSiteIds Sites the current edition may surface (see PluginSettings::getInScopeSiteIds)
     * @param int|null $reviewerId
     * @return Query
     * @see \webhubworks\verifiedelements\models\ElementData Populate this object with the results of the query
     */
    public static function expiredVerifiableElements(array $elementTypes, array $inScopeSiteIds, ?int $reviewerId = null): Query
    {
        $queries = array_map(
            static fn(string $elementType) => self::expiredElements(
                ElementType::from($elementType),
                $inScopeSiteIds,
                $reviewerId
            ),
            $elementTypes
        );

        return self::unionAll($queries);
    }

    /**
     * Returns a query for all verifiable entries that have verification dates in the past.
     *
     * @param int[] $inScopeSiteIds Sites the current edition may surface (see PluginSettings::getInScopeSiteIds)
     * @return Query
     */
    public static function expiredVerifiableEntries(array $inScopeSiteIds): Query
    {
        return self::expiredElements(ElementType::Entry, $inScopeSiteIds);
    }

    /**
     * Returns a query for all verifiable assets that have verification dates in the past.
     *
     * @param int[] $inScopeSiteIds Sites the current edition may surface (see PluginSettings::getInScopeSiteIds)
     * @return Query
     */
    public static function expiredVerifiableAssets(array $inScopeSiteIds): Query
    {
        return self::expiredElements(ElementType::Asset, $inScopeSiteIds);
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
                'ves.defaultPeriod',
            ])
            ->from(['s' => '{{%sections}}'])
            ->innerJoin(
                '{{%sections_sites}} ss',
                '[[ss.sectionId]] = [[s.id]] AND [[ss.siteId]] = :siteId',
                [':siteId' => $siteId]
            )
            ->leftJoin(
                PluginTable::CONTAINERS . ' ves',
                '[[ves.containerId]] = [[s.id]] AND [[ves.siteId]] = :vesSiteId AND [[ves.elementType]] = :entryType',
                [':vesSiteId' => $siteId, ':entryType' => ElementType::Entry->value]
            );
    }

    /**
     * Returns a query for volumes that have settings.
     *
     * Volumes have no site dimension in Craft, so the caller passes one representative site
     * (the primary site) to read from - the save path writes identical rows for every site.
     *
     * @param int $siteId
     * @return Query
     * @see \webhubworks\verifiedelements\services\singletons\PluginSettings::getAllVolumesWithSettings
     * @see \webhubworks\verifiedelements\services\singletons\PluginSettings::saveVolumeSettings
     */
    public static function volumesWithSettings(int $siteId): Query
    {
        return (new Query())
            ->select([
                'v.id',
                'v.uid',
                'v.name',
                'v.handle',
                'ves.reviewerId',
                'ves.enabled',
                'ves.defaultPeriod',
            ])
            ->from(['v' => '{{%volumes}}'])
            ->leftJoin(
                PluginTable::CONTAINERS . ' ves',
                '[[ves.containerId]] = [[v.id]] AND [[ves.siteId]] = :vesSiteId AND [[ves.elementType]] = :assetType',
                [':vesSiteId' => $siteId, ':assetType' => ElementType::Asset->value]
            )
            ->orderBy(['v.name' => SORT_ASC]);
    }

    /**
     * Returns the raw container-settings rows (reviewer, enabled flag, default period) stored for
     * one element type on one site. Element-agnostic: pass any registered element type's value.
     *
     * @param int $siteId
     * @param string $elementType
     * @return Query
     */
    public static function containerSettings(int $siteId, string $elementType): Query
    {
        return (new Query())
            ->select(['containerId', 'reviewerId', 'enabled', 'defaultPeriod'])
            ->from(PluginTable::CONTAINERS)
            ->where(['siteId' => $siteId, 'elementType' => $elementType]);
    }

    /**
     * Returns a query for a container's default settings saved on the plugin's Settings CP page.
     * The container's name/handle come from the table matching its element type (sections for
     * entries, volumes for assets).
     *
     * @param int $containerId
     * @param int $siteId
     * @param string $elementType
     * @return Query
     * @see \webhubworks\verifiedelements\services\singletons\PluginSettings::getDefaultSettingsForContainer()
     */
    public static function containerDefaults(int $containerId, int $siteId, string $elementType): Query
    {
        $containerTable = ElementType::from($elementType)->containerTable();

        return (new Query())
            ->select([
                'ves.containerId AS id',
                'c.name',
                'c.handle',
                'ves.siteId',
                'ves.reviewerId',
                'ves.defaultPeriod AS period',
            ])
            ->from(['ves' => PluginTable::CONTAINERS])
            ->innerJoin(['c' => $containerTable], '[[c.id]] = [[ves.containerId]]')
            ->where([
                'ves.containerId' => $containerId,
                'ves.siteId' => $siteId,
                'ves.elementType' => $elementType,
                'ves.enabled' => true,
            ]);
    }

    /**
     * IDs of the users currently assigned as reviewers on elements of the given type, restricted
     * to enabled containers and in-scope sites. Feeds the per-reviewer dashboard sources: a
     * reviewer "exists" for the dashboard when they have at least one assignment it can show,
     * regardless of what permissions they hold.
     *
     * @param ElementType $elementType
     * @param int[] $inScopeSiteIds
     * @return Query
     */
    public static function assignedReviewerIds(ElementType $elementType, array $inScopeSiteIds): Query
    {
        return (new Query())
            ->select('veea.reviewerId')
            ->distinct()
            ->from(['veea' => PluginTable::ATTRIBUTES])
            ->innerJoin(
                ['elementRecord' => $elementType->elementTable()],
                '[[elementRecord.id]] = [[veea.elementId]]'
            )
            ->innerJoin(
                ['ves' => PluginTable::CONTAINERS],
                sprintf(
                    '[[ves.containerId]] = [[elementRecord.%s]] AND [[ves.siteId]] = [[veea.siteId]] AND [[ves.enabled]] = 1',
                    $elementType->containerIdColumn()
                )
            )
            ->andWhere(['ves.elementType' => $elementType->value])
            ->andWhere(['not', ['veea.reviewerId' => null]])
            ->andWhere(['veea.siteId' => $inScopeSiteIds]);
    }


    // PRIVATE HELPERS
    // =============================================================================================
    //
    // The builders below parameterize FACTS (table names, the container-id column, the FQCN),
    // never LOGIC. If a future element type needs genuinely different query logic - an extra
    // filter, a different join topology - give that type its own concrete method instead of
    // adding conditionals here. All per-type queries must select the same column list in the
    // same order: ElementData::fromArray() and UNION ALL both depend on it.
    //
    // Conditions that vary per type use array syntax (auto-named params), never named params -
    // a UNION merges both subqueries' params, and identical names would silently collide.

    /**
     * Returns a query for all elements of one type assigned to a reviewer.
     *
     * @param ElementType $elementType
     * @param int $userId The Reviewer
     * @param int|null $siteId Narrows to a single site within the in-scope set
     * @param int[] $inScopeSiteIds Sites the current edition may surface (see PluginSettings::getInScopeSiteIds)
     * @return Query
     * @see \webhubworks\verifiedelements\models\ElementData
     */
    private static function reviewerElements(
        ElementType $elementType,
        int         $userId,
        ?int        $siteId,
        array       $inScopeSiteIds,
    ): Query {
        $containerIdColumn = $elementType->containerIdColumn();

        $query = (new Query())
            ->select([
                'elements.type',
                'veea.id AS rowId',
                'veea.elementId AS id',
                'veea.siteId',
                'veea.reviewerId',
                'veea.verifiedUntilDate',
                sprintf('elementRecord.%s AS containerId', $containerIdColumn),
                'container.name AS containerName',
                'container.handle AS containerHandle',
                'es.title',
                'es.slug',
                'es.dateUpdated',
                'sites.name AS siteName',
                'sites.handle AS siteHandle',
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
            ->leftJoin(
                ['elementRecord' => $elementType->elementTable()],
                '[[elementRecord.id]] = [[veea.elementId]]'
            )
            ->leftJoin(
                ['container' => $elementType->containerTable()],
                sprintf('[[container.id]] = [[elementRecord.%s]]', $containerIdColumn)
            )
            ->leftJoin('{{%sites}}', '[[sites.id]] = [[veea.siteId]]')
            ->where(['veea.reviewerId' => $userId])
            ->andWhere(['elements.type' => $elementType->value])
            ->andWhere('elements.canonicalId IS null')
            // Don't return rows for sites not supported by the plugin's current edition.
            ->andWhere(['veea.siteId' => $inScopeSiteIds]);

        if ($siteId !== null) {
            $query->andWhere(['veea.siteId' => $siteId]);
        }

        return $query;
    }

    /**
     * Returns a query for all verifiable elements of one type with verification dates in the
     * past, limited to containers that are enabled in the plugin's settings.
     *
     * @param ElementType $elementType
     * @param int[] $inScopeSiteIds Sites the current edition may surface (see PluginSettings::getInScopeSiteIds)
     * @param int|null $reviewerId
     * @return Query
     * @see \webhubworks\verifiedelements\models\ElementData
     */
    private static function expiredElements(ElementType $elementType, array $inScopeSiteIds, ?int $reviewerId = null): Query
    {
        $now = Db::prepareDateForDb(DateHelper::now());
        $containerIdColumn = $elementType->containerIdColumn();

        $query = (new Query())
            ->select([
                'elements.type',
                'veea.id AS rowId',
                'veea.elementId AS id',
                'veea.siteId',
                'veea.reviewerId',
                'veea.verifiedUntilDate',
                sprintf('elementRecord.%s AS containerId', $containerIdColumn),
                'container.name AS containerName',
                'container.handle AS containerHandle',
                'es.title',
                'es.slug',
                'es.dateUpdated',
                'sites.name AS siteName',
                'sites.handle AS siteHandle',
            ])
            ->from(['veea' => PluginTable::ATTRIBUTES])
            ->leftJoin('{{%elements}}', '[[elements.id]] = [[veea.elementId]]')
            ->leftJoin(
                '{{%elements_sites}} es',
                '[[es.elementId]] = [[veea.elementId]] AND [[es.siteId]] = [[veea.siteId]]'
            )
            ->leftJoin('{{%sites}}', '[[sites.id]] = [[veea.siteId]]')
            ->leftJoin(
                ['elementRecord' => $elementType->elementTable()],
                '[[elementRecord.id]] = [[veea.elementId]]'
            )
            ->innerJoin(
                ['container' => $elementType->containerTable()],
                sprintf('[[container.id]] = [[elementRecord.%s]]', $containerIdColumn)
            )
            ->innerJoin(
                ['ves' => PluginTable::CONTAINERS],
                sprintf(
                    '[[ves.containerId]] = [[elementRecord.%s]] AND [[ves.siteId]] = [[veea.siteId]] AND [[ves.enabled]] = 1',
                    $containerIdColumn
                )
            )
            ->where(['<', 'veea.verifiedUntilDate', $now])
            ->andWhere(['ves.elementType' => $elementType->value])
            ->andWhere(['elements.type' => $elementType->value])
            ->andWhere(['elements.enabled' => true])
            ->andWhere(['es.enabled' => true])
            ->andWhere('elements.canonicalId IS null')
            // Don't return rows for sites not supported by the plugin's current edition.
            ->andWhere(['veea.siteId' => $inScopeSiteIds]);

        if ($reviewerId !== null) {
            $query->andWhere(['veea.reviewerId' => $reviewerId]);
        }

        return $query;
    }

    /**
     * Combines per-type queries with UNION ALL, wrapped as a subquery so the caller's
     * `orderBy`/`limit`/`offset` apply across the whole result set.
     *
     * All queries must select the same column list in the same order.
     *
     * @param Query[] $queries At least one query
     * @return Query
     */
    private static function unionAll(array $queries): Query
    {
        $unionQuery = array_shift($queries);

        // A single query needs no union wrapper.
        if (empty($queries)) {
            return $unionQuery;
        }

        foreach ($queries as $query) {
            $unionQuery->union($query, true);
        }

        return (new Query())
            ->select('*')
            ->from(['elements_union' => $unionQuery]);
    }
}
