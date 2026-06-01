<?php

namespace webhubworks\verifiedentries\elements\conditions;

use Craft;
use craft\base\conditions\BaseSelectConditionRule;
use craft\base\ElementInterface;
use craft\elements\conditions\ElementConditionRuleInterface;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use webhubworks\verifiedentries\behaviors\EntryQueryBehavior;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\enums\VerificationStatus;
use webhubworks\verifiedentries\VerifiedEntries;

class VerifiedConditionRule extends BaseSelectConditionRule implements ElementConditionRuleInterface
{
    /** @inheritDoc */
    public function getLabel(): string
    {
        return Craft::t(VerifiedEntries::HANDLE, 'Verification Status');
    }

    /** @inheritDoc */
    public function getExclusiveQueryParams(): array
    {
        return ['isVerified', 'isAssigned', 'verifiedUntilDate'];
    }

    /** @inheritDoc */
    protected function options(): array
    {
        return [
            ['value' => VerificationStatus::Verified->handle(), 'label' => VerificationStatus::Verified->label()],
            ['value' => VerificationStatus::Expired->handle(), 'label' => VerificationStatus::Expired->label()],
            ['value' => VerificationStatus::Unassigned->handle(), 'label' => VerificationStatus::Unassigned->label()],
            ['value' => VerificationStatus::Indefinite->handle(), 'label' => VerificationStatus::Indefinite->label()],
        ];
    }

    /** @inheritDoc */
    public function modifyQuery(ElementQueryInterface $query): void
    {
        /** @var EntryQuery|EntryQueryBehavior $query */
        match ($this->value) {
            VerificationStatus::Verified->handle() => $query->andWhere(['and',
                'veea.verifiedUntilDate IS NOT NULL',
                'veea.verifiedUntilDate >= UTC_TIMESTAMP()',
            ])->isAssigned(true),
            VerificationStatus::Expired->handle() => $query->isVerified(false),
            VerificationStatus::Unassigned->handle() => $query->isAssigned(false),
            VerificationStatus::Indefinite->handle() => $query->andWhere('veea.verifiedUntilDate IS NULL'),
            default => null,
        };
    }

    /** @inheritDoc */
    public function matchElement(ElementInterface $element): bool
    {
        /** @var Entry|VerifiableBehavior $element */
        return $element->getVerificationStatus()->handle() === $this->value;
    }
}
