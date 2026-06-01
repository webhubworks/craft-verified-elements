<?php

namespace webhubworks\verifiedentries\elements;

use Craft;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use webhubworks\verifiedentries\enums\Permission;
use webhubworks\verifiedentries\enums\VerificationStatus;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Verified Entry element type
 */
class VerifiedEntry extends Entry
{
    public static function refHandle(): ?string
    {
        return 'verifiedEntry';
    }

    public static function find(): EntryQuery
    {
        return new EntryQuery(Entry::class);
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'section',
            'postDate',
            'isVerified',
            'verifiedUntilDate',
            'reviewer',
        ];
    }

    protected static function defineSources(string $context = null): array
    {
        $enabledSectionIds = VerifiedEntries::getInstance()->getSectionSettings()->getEnabledSectionIds();
        $currentUser = Craft::$app->getUser();

        $sources = [
            [
                'key' => VerificationStatus::Expired->handle(),
                'label' => VerificationStatus::Expired->label(),
                'criteria' => [
                    'isVerified' => false,
                    'sectionId' => $enabledSectionIds,
                    'status' => 'live',
                ]
            ],
            [
                'key' => 'upcoming',
                'label' => Craft::t('app', 'Pending'),
                'criteria' => [
                    'isVerified' => true,
                    'sectionId' => $enabledSectionIds,
                    'status' => 'live',
                    'verifiedUntil' => '< ' . (DateTimeHelper::nextMonth())->format('Y-m-d'),
                ]
            ],
            [
                'key' => VerificationStatus::Verified->handle(),
                'label' => VerificationStatus::Verified->label(),
                'criteria' => [
                    'isVerified' => true,
                    'sectionId' => $enabledSectionIds,
                    'status' => 'live',
                ]
            ],
            [
                'key' => VerificationStatus::Unassigned->handle(),
                'label' => VerificationStatus::Unassigned->label(),
                'criteria' => [
                    'isUnassigned' => true,
                    'sectionId' => $enabledSectionIds,
                    'status' => 'live',
                ]
            ],
            [
                'heading' => Craft::t(VerifiedEntries::HANDLE, 'Reviewer'),
            ],
            [
                'key' => 'mine',
                'label' => $currentUser->getIdentity()->getFriendlyName(),
                'criteria' => [
                    'reviewerId' => $currentUser->id,
                    'status' => 'live',
                    'sectionId' => $enabledSectionIds,
                ]
            ]
        ];

        $reviewers = User::find()
            ->can(Permission::VerifyEntries->value)
            ->id(['not', $currentUser->id])
            ->all();

        foreach ($reviewers as $reviewer) {
            /** @var User $reviewer */
            $sources[] = [
                'key' => 'reviewer-' . $reviewer->id,
                'label' => $reviewer->getFriendlyName(),
                'criteria' => [
                    'reviewerId' => $reviewer->id,
                    'status' => 'live',
                    'sectionId' => $enabledSectionIds,
                ],
            ];
        }

        return $sources;
    }
}
