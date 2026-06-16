<?php /** @noinspection PhpUnhandledExceptionInspection */

namespace webhubworks\verifiedentries\elements;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use webhubworks\verifiedentries\enums\Permission;
use webhubworks\verifiedentries\enums\ReviewerStatus;
use webhubworks\verifiedentries\enums\VerificationStatus;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Entry subtype that powers the plugin's dashboard element index, defining its sidebar sources
 * and default table columns.
 */
class VerifiedEntry extends Entry
{
    /** @inheritDoc */
    public static function refHandle(): ?string
    {
        return 'verifiedEntry';
    }

    /** @inheritDoc */
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

    /** @inheritDoc */
    protected static function defineSources(string $context = null): array
    {
        $enabledSectionIds = VerifiedEntries::getInstance()->getPluginSettings()->getEnabledSectionIds();
        $currentUser = Craft::$app->getUser();

        /** @noinspection PhpUndefinedMethodInspection */
        $unassignedCount = Entry::find()
            ->sectionId($enabledSectionIds)
            ->site(Craft::$app->getRequest()->getQueryParam('site'))
            ->status(Entry::STATUS_LIVE)
            ->isAssigned(false)
            ->count();

        $sources = [
            [
                'key' => VerificationStatus::Expired->handle(),
                'label' => VerificationStatus::Expired->label(),
                'criteria' => [
                    'isVerified' => false,
                    'sectionId' => $enabledSectionIds,
                ]
            ],
            [
                'key' => 'upcoming',
                'label' => Craft::t(VerifiedEntries::HANDLE, 'Imminent'),
                'criteria' => [
                    'isVerified' => true,
                    'sectionId' => $enabledSectionIds,
                    'verifiedUntil' => '< ' . (DateTimeHelper::nextMonth())->format('Y-m-d'),
                ],
            ],
            [
                'key' => VerificationStatus::Verified->handle(),
                'label' => VerificationStatus::Verified->label(),
                'criteria' => [
                    'isVerified' => true,
                    'sectionId' => $enabledSectionIds,
                ]
            ],
            [
                'key' => ReviewerStatus::Unassigned->handle(),
                'label' => ReviewerStatus::Unassigned->label(),
                'badgeCount' => $unassignedCount > 0 ? $unassignedCount : null,
                'criteria' => [
                    'isAssigned' => false,
                    'sectionId' => $enabledSectionIds,
                ],
            ],
            [
                'heading' => Craft::t(VerifiedEntries::HANDLE, 'Reviewer'),
            ],
            [
                'key' => 'mine',
                'label' => $currentUser->getIdentity()->getFriendlyName(),
                'criteria' => [
                    'reviewerId' => $currentUser->id,
                    'sectionId' => $enabledSectionIds,
                ]
            ],
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
                    'sectionId' => $enabledSectionIds,
                ],
            ];
        }

        return $sources;
    }
}
