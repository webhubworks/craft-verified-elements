<?php

namespace webhubworks\verifiedentries\elements\conditions;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use webhubworks\verifiedentries\VerifiedEntries;

class VerifiedConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    /**
     * @inheritDoc
     */
    public function getLabel(): string
    {
        return Craft::t(VerifiedEntries::HANDLE, 'Verified');
    }

    /**
     * @inheritDoc
     */
    public function getExclusiveQueryParams(): array
    {
        return ['verifiedUntilDate'];
    }

    /**
     * @inheritDoc
     */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var EntryQuery $query */
        $query->isVerified($this->value);
    }

    /**
     * @inheritDoc
     */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Entry $element */
        return $element->isVerified === $this->value;
    }
}
