<?php

namespace webhubworks\verifiedentries\elements\conditions;

use Craft;
use craft\base\conditions\BaseDateRangeConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use Throwable;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedentries\helpers\Log;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Condition rule for filtering entries by their "Verified until" date.
 *
 * To find this in the CP:
 * 1. Go to an entries listing page.
 * 2. Click the filter icon in the search bar.
 * 3. Select "Add a filter" and choose "Verified until".
 * 4. A new dropdown field appears. Those options come from this class.
 */
class VerifiedUntilDateConditionRule extends BaseDateRangeConditionRule implements ElementConditionRuleInterface
{
    /** @inheritDoc */
    public function getLabel(): string
    {
        return Craft::t(VerifiedEntries::HANDLE, 'Verified until');
    }

    /** @inheritDoc */
    public function getExclusiveQueryParams(): array
    {
        return ['verifiedUntilDate'];
    }

    /** @inheritDoc */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var EntryQuery|VerifiableQueryBehavior $query */
        $query->verifiedUntilDate($this->queryParamValue());
    }

    /** @inheritDoc */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Entry|VerifiableBehavior $element */
        try {
            return $this->matchValue($element->getVerifiedUntilDate());
        }
        catch (Throwable $exception) {
            Log::error($exception->getMessage(), $exception);
        }

        return false;
    }
}
