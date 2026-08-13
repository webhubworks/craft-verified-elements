<?php

/** @noinspection PhpSameParameterValueInspection */

namespace webhubworks\verifiedelements\behaviors;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\helpers\Db;
use webhubworks\verifiedelements\base\VerifiableQueryInterface;
use webhubworks\verifiedelements\db\PluginTable;
use yii\base\Behavior;

/**
 * This behavior attaches to every element query and surfaces this plugin's verification state on it.
 *
 * On prepare, it LEFT JOINs the verification attributes table (matched per element + site),
 * so each result carries its `verifiedUntilDate` and `reviewerId`, and it registers the filter
 * params `isVerified`, `isAssigned`, `reviewerId`, and `verifiedUntil`, which translate into
 * WHERE clauses.
 *
 * The JOIN is added in two places: to the outer query on BEFORE_PREPARE, and to the subquery on
 * AFTER_PREPARE (the subquery's `elements_sites` join isn't present until after that event fires).
 *
 * Nested elements (e.g. Matrix entries, which set `ownerId`) are skipped. Verification applies
 * only to top-level elements.
 *
 * @property ElementQuery $owner
 */
class VerifiableQueryBehavior extends Behavior implements VerifiableQueryInterface
{
    public const NAME = 'verified-elements.verifiable-query';
    public ?bool $isVerified = null;
    public ?bool $isAssigned = null;
    public ?int $reviewerId = null;
    public ?string $verifiedUntil = null;

    /** @inheritdoc */
    public function events(): array
    {
        return [
            ElementQuery::EVENT_BEFORE_PREPARE => 'beforePrepare',
            ElementQuery::EVENT_AFTER_PREPARE => 'afterPrepare',
        ];
    }

    /**
     * Joins the verification table to the outer query and applies any filter criteria set on the query.
     * @see ElementQuery::beforePrepare()
     */
    public function beforePrepare(): void
    {
        // If this is an entry, skip adding JOIN and SELECT for matrix entries since they're not
        // applicable to the plugin.
        if (isset($this->owner->ownerId)) {
            return;
        }

        /** @var Query $query */
        $query = $this->owner->query;

        if (!$this->hasJoin($query, 'veea')) {
            $query->leftJoin(
                ['veea' => PluginTable::ATTRIBUTES],
                '[[veea.elementId]] = [[elements.id]] AND [[veea.siteId]] = [[elements_sites.siteId]]'
            );
        }

        $query->addSelect([
            'veea.verifiedUntilDate',
            'veea.reviewerId',
        ]);

        if ($this->isVerified !== null) {
            $this->isVerified($this->isVerified);
        }

        if ($this->isAssigned !== null) {
            $this->isAssigned($this->isAssigned);
        }

        if ($this->reviewerId !== null) {
            $this->reviewerId($this->reviewerId);
        }

        if ($this->verifiedUntil !== null) {
            $this->verifiedUntilDate($this->verifiedUntil);
        }
    }

    /**
     * Joins the verification table to the subQuery; must run after beforePrepare() because
     * elements_sites isn't added to the subQuery until after that event fires.
     * @noinspection PhpUnused
     * @see          ElementQuery::afterPrepare()
     */
    public function afterPrepare(): void
    {
        // If this is an entry, skip adding JOIN and SELECT for matrix entries since they're not
        // applicable to the plugin.
        if (isset($this->owner->ownerId)) {
            return;
        }

        /** @var Query $subQuery */
        $subQuery = $this->owner->subQuery;

        // Join our `verifiedelements_attributes` table
        if (!$this->hasJoin($subQuery, 'veea')) {
            $subQuery->leftJoin(
                ['veea' => PluginTable::ATTRIBUTES],
                '[[veea.elementId]] = [[elements.id]] AND [[veea.siteId]] = [[elements_sites.siteId]]'
            );
        }
    }

    /** @inheritDoc */
    public function isVerified(bool $value = true): ElementQuery
    {
        $query = $this->owner;

        if ($value) {
            $query->andWhere(['or',
                'veea.verifiedUntilDate IS NULL',
                'veea.verifiedUntilDate >= UTC_TIMESTAMP()',
            ]);
        } else {
            $query->andWhere(['and',
                'veea.verifiedUntilDate IS NOT NULL',
                'veea.verifiedUntilDate < UTC_TIMESTAMP()',
            ]);
        }

        return $query;
    }

    /** @inheritDoc */
    public function isAssigned(bool $value = true): ElementQuery
    {
        $query = $this->owner;

        if ($value) {
            $query->andWhere('veea.reviewerId IS NOT NULL');
        } else {
            $query->andWhere('veea.reviewerId IS NULL');
        }

        return $query;
    }

    /** @inheritDoc */
    public function reviewerId(int|array|null $value = null): ElementQuery
    {
        $query = $this->owner;

        if (is_array($value) || is_int($value)) {
            $query->andWhere(['veea.reviewerId' => $value]);
        } elseif ($value === null) {
            $query->andWhere('veea.reviewerId IS null');
        }

        return $query;
    }

    /** @inheritDoc */
    public function verifiedUntilDate(mixed $value): ElementQuery
    {
        $query = $this->owner;

        $query->andWhere(Db::parseDateParam('veea.verifiedUntilDate', $value));

        return $query;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Check if a join exists on the table to prevent the query from being executed.
     *
     * @param Query $query
     * @param string $alias
     * @return bool
     */
    private function hasJoin(Query $query, string $alias): bool
    {
        if (!$query->join) {
            return false;
        }

        foreach ($query->join as $join) {
            if (is_array($join[1])) {
                $aliases = array_keys($join[1]);
                if (in_array($alias, $aliases, true)) {
                    return true;
                }
            } else {
                return $query->isJoined($alias);
            }
        }

        return false;
    }
}
