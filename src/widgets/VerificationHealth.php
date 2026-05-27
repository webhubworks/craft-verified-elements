<?php

namespace webhubworks\verifiedentries\widgets;

use Craft;
use craft\base\Widget;
use craft\elements\Entry;
use craft\helpers\Cp;
use webhubworks\verifiedentries\enums\VerificationStatus;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Verification Health widget type
 */
class VerificationHealth extends Widget
{
    /** @inheritDoc */
    public static function displayName(): string
    {
        return Craft::t(VerifiedEntries::HANDLE, 'Verification Health');
    }

    /** @inheritDoc */
    public static function isSelectable(): bool
    {
        return true;
    }

    /** @inheritDoc */
    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public static function icon(): ?string
    {
        return 'heart';
    }

    /** @inheritDoc */
    public function getBodyHtml(): ?string
    {
        $enabledSectionIds = VerifiedEntries::getInstance()->getSectionSettings()->getEnabledSectionIds();

        $totalEntryCount = Entry::find()
            ->status('live')
            ->site('*')
            ->section('*')
            ->count();

        $verifiedEntryCount = Entry::find()
            ->status('live')
            ->site('*')
            ->sectionId($enabledSectionIds)
            ->isVerified(true)
            ->isUnassigned(false)
            ->count();

        $expiredEntryCount = Entry::find()
            ->status('live')
            ->site('*')
            ->sectionId($enabledSectionIds)
            ->isVerified(false)
            ->count();

        $unassignedEntryCount = Entry::find()
            ->status('live')
            ->site('*')
            ->sectionId($enabledSectionIds)
            ->isUnassigned(true)
            ->count();

        $statuses = [
            [
                'label' => VerificationStatus::Verified->label(),
                'count' => $verifiedEntryCount,
                'icon' => Cp::statusIndicatorHtml(
                    VerificationStatus::Verified->handle(),
                    ['color' => VerificationStatus::Verified->color()]
                ),
            ],
            [
                'label' => VerificationStatus::Expired->label(),
                'count' => $expiredEntryCount,
                'icon' => Cp::statusIndicatorHtml(
                    VerificationStatus::Expired->handle(),
                    ['color' => VerificationStatus::Expired->color()]
                ),
            ],
            [
                'label' => VerificationStatus::Unassigned->label(),
                'count' => $unassignedEntryCount,
                'icon' => Cp::statusIndicatorHtml(
                    VerificationStatus::Unassigned->handle(),
                    ['color' => VerificationStatus::Unassigned->color()]
                ),
            ],
        ];

        return Craft::$app->getView()->renderTemplate(
            VerifiedEntries::HANDLE . '/_widgets/health.twig',
            [
                'totalCount' => $totalEntryCount,
                'verifiedCount' => $verifiedEntryCount,
                'unassignedCount' => $unassignedEntryCount,
                'expiredCount' => $expiredEntryCount,
                'statuses' => $statuses,
                'statusColors' => [
                    'verified' => VerificationStatus::Verified->cssColor(),
                    'unassigned' => VerificationStatus::Unassigned->cssColor(),
                    'expired' => VerificationStatus::Expired->cssColor(),
                ],
            ]
        );
    }
}
