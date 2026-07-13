<?php

namespace webhubworks\verifiedelements\base;

use craft\elements\db\ElementQuery;
use webhubworks\verifiedelements\behaviors\VerifiableQueryBehavior;

/**
 * Describes the filter methods that VerifiableQueryBehavior adds to an element query.
 *
 * No query class implements this interface; it exists so docblocks can type a
 * behavior-carrying query as `(EntryQuery|AssetQuery)&VerifiableQueryInterface` for static
 * analysis. See VerifiableElementInterface for the full rationale.
 *
 * @see VerifiableQueryBehavior
 * @see VerifiableElementInterface
 */
interface VerifiableQueryInterface
{
    /**
     * Query param for filtering elements by whether their "Verified until" date field is still in
     * the future.
     *
     * @param bool $value
     * @return ElementQuery
     */
    public function isVerified(bool $value = true): ElementQuery;

    /**
     * Query param for filtering elements that have or haven't been assigned to a Reviewer.
     *
     * @param bool $value
     * @return ElementQuery
     */
    public function isAssigned(bool $value = true): ElementQuery;

    /**
     * Query param for filtering elements by their Reviewer (Craft User) ID.
     *
     * @param int|array|null $value
     * @return ElementQuery
     */
    public function reviewerId(int|array|null $value = null): ElementQuery;

    /**
     * Query param for filtering elements by their "Verified until" date field.
     *
     * @param mixed $value
     * @return ElementQuery
     */
    public function verifiedUntilDate(mixed $value): ElementQuery;
}
