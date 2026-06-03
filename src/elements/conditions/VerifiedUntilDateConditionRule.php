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
 * Condition rule that filters entries by their "Verified until" date.
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
