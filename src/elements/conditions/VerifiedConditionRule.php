<?php

namespace webhubworks\verifiedelements\elements\conditions;

use Craft;
use craft\base\conditions\BaseLightswitchConditionRule;
use craft\base\Element;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQuery;
use craft\elements\db\ElementQueryInterface;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedelements\Plugin;

/**
 * Condition rule for filtering elements by their Verification Status.
 *
 * To find this in the CP:
 * 1. Go to an element listing page (entries or assets).
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
        return Craft::t(Plugin::HANDLE, 'Verified');
    }

    /** @inheritDoc */
    public function getExclusiveQueryParams(): array
    {
        return ['isVerified'];
    }

    /** @inheritDoc */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var ElementQuery|VerifiableQueryBehavior $query */
        $query->isVerified($this->value);
    }

    /** @inheritDoc */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Element|VerifiableBehavior $element */
        return $element->getIsVerified() === $this->value;
    }
}
