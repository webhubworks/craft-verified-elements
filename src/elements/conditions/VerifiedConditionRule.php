<?php

namespace webhubworks\verifiedentries\elements\conditions;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Condition rule for filtering entries by their Verification Status.
 *
 * To find this in the CP:
 * 1. Go to an entries listing page.
 * 2. Click the filter icon in the search bar.
 * 3. Select "Add a filter" and choose "Verified".
 * 4. A new dropdown field appears. Those options come from this class.
 *
 * @see VerificationStatus
 */
class VerifiedConditionRule extends BaseLightswitchConditionRule implements ElementConditionRuleInterface
{
    /** @inheritDoc */
    public function getLabel(): string
    {
        return Craft::t(VerifiedEntries::HANDLE, 'Verified');
    }

    /** @inheritDoc */
    public function getExclusiveQueryParams(): array
    {
        return ['isVerified'];
    }

    /** @inheritDoc */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var EntryQuery|VerifiableQueryBehavior $query */
        $query->isVerified($this->value);
    }

    /** @inheritDoc */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Entry|VerifiableBehavior $element */
        return $element->getIsVerified() === $this->value;
    }
}
