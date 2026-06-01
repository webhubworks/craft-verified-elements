<?php /** @noinspection PhpSameParameterValueInspection */

namespace webhubworks\verifiedentries\behaviors;

use craft\db\Query;
use craft\elements\db\ElementQuery;
use craft\elements\db\EntryQuery;
use craft\helpers\Db;
use webhubworks\verifiedentries\db\PluginTable;
use yii\base\Behavior;

/**
 * @property EntryQuery $owner
 */
class VerifiableQueryBehavior extends Behavior
{
    public const NAME = 'verified-entries.verifiable-query';
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
        // Skip adding JOIN and SELECT for nested matrix entries
        // since they aren't applicable to the plugin.
        if ($this->owner->ownerId !== null) {
            return;
        }

        /** @var Query $query */
        $query = $this->owner->query;

        if (! $this->hasJoin($query, 'veea')) {
            $query->leftJoin(
                ['veea' => PluginTable::ENTRIES],
                '[[veea.entryId]] = [[elements.id]] AND [[veea.siteId]] = [[elements_sites.siteId]]'
            );
        }

        // Select custom columns. Craft will attempt to assign anything defined here
        // to the Entry element when populating it! Fortunately, your Behavior can also supply
        // properties.
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
        // Skip adding JOIN for nested matrix entries since they aren't applicable to the plugin.
        if ($this->owner->ownerId !== null) {
            return;
        }

        /** @var Query $subQuery */
        $subQuery = $this->owner->subQuery;

        // Join our `verifiedentries_entryattributes` table
        if (! $this->hasJoin($subQuery, 'veea')) {
            $subQuery->leftJoin(
                ['veea' => PluginTable::ENTRIES],
                '[[veea.entryId]] = [[elements.id]] AND [[veea.siteId]] = [[elements_sites.siteId]]'
            );
        }
    }

    /**
     * Query param for filtering entries by whether their "Verified until" date field is still in
     * the future.
     *
     * @param bool $value
     * @return EntryQuery
     */
    public function isVerified(bool $value = true): EntryQuery
    {
        $query = $this->owner;

        if ($value) {
            $query->andWhere(['or',
                'veea.verifiedUntilDate IS NULL',
                'veea.verifiedUntilDate >= UTC_TIMESTAMP()',
            ]);
        }
        else {
            $query->andWhere(['and',
                'veea.verifiedUntilDate IS NOT NULL',
                'veea.verifiedUntilDate < UTC_TIMESTAMP()',
            ]);
        }

        return $query;
    }

    /**
     * Query param for filtering entries that have or haven't been assigned to a reviewer
     *
     * @param bool $value
     * @return EntryQuery
     */
    public function isAssigned(bool $value = true): EntryQuery
    {
        $query = $this->owner;

        if ($value) {
            $query->andWhere('veea.reviewerId IS NOT NULL');
        }
        else {
            $query->andWhere('veea.reviewerId IS NULL');
        }

        return $query;
    }

    /**
     * Query param for filtering entries by their Reviewer (Craft User) ID.
     *
     * @param int|array|null $value
     * @return EntryQuery
     */
    public function reviewerId(int|array|null $value = null): EntryQuery
    {
        $query = $this->owner;

        if (is_array($value) || is_int($value)) {
            $query->andWhere(['veea.reviewerId' => $value]);
        }
        elseif ($value === null) {
            $query->andWhere('veea.reviewerId IS null');
        }

        return $query;
    }

    /**
     * Query param for filtering entries by their "Verified until" date field.
     *
     * @param mixed $value
     * @return EntryQuery
     */
    public function verifiedUntilDate(mixed $value): EntryQuery
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
        if (! $query->join) {
            return false;
        }

        foreach ($query->join as $join) {
            if (is_array($join[1])) {
                $aliases = array_keys($join[1]);
                if (in_array($alias, $aliases, true)) {
                    return true;
                }
            }
            else {
                return $query->isJoined($alias);
            }
        }

        return false;
    }
}
