<?php

namespace webhubworks\verifiedelements\elements\conditions;

use Craft;
use craft\base\conditions\BaseElementSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedelements\Plugin;

/**
 * Condition rule for filtering entries by their Reviewer.
 *
 * To find this in the CP:
 * 1. Go to an entries listing page.
 * 2. Click the filter icon in the search bar.
 * 3. Select "Add a filter" and choose "Reviewer".
 * 4. A new dropdown field appears. Those options come from this class.
 */
class ReviewerConditionRule extends BaseElementSelectConditionRule implements ElementConditionRuleInterface
{
    /** @inheritDoc */
    protected function elementType(): string
    {
        return User::class;
    }

    /** @inheritDoc */
    public function getLabel(): string
    {
        return Craft::t(Plugin::HANDLE, 'Reviewer');
    }

    /** @inheritDoc */
    protected  function allowMultiple(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public function getExclusiveQueryParams(): array
    {
        return ['reviewer', 'reviewerId'];
    }

    /** @inheritDoc */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var EntryQuery|VerifiableQueryBehavior $query */
        $query->reviewerId($this->getElementIds());
    }

    /** @inheritDoc */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Entry|VerifiableBehavior $element */
        return $this->matchValue($element->getReviewerId());
    }
}
