<?php

namespace webhubworks\verifiedelements\services;

use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use webhubworks\verifiedelements\elements\VerifiedAsset;
use webhubworks\verifiedelements\elements\VerifiedEntry;
use webhubworks\verifiedelements\Plugin;
use webhubworks\verifiedelements\enums\Permission;
use webhubworks\verifiedelements\enums\ReviewerStatus;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\services\singletons\PluginSettings;

/**
 * Builds components for Craft's "Element Index" view in the CP.
 *
 * An example of this would be in CP > Verified Elements > Entries. It shows the listing of entries
 * enabled in the plugin. On the left-hand side, there's a sub-menu of "sources" that,
 * when clicked, filters the entries by their verification and reviewer states.
 *
 * @see VerifiedAsset::defineSources()
 * @see VerifiedEntry::defineSources()
 */
class ElementIndexSourcesBuilder
{
    public function __construct(
        private readonly string                $elementType,
        private readonly string                $containerIdQueryParam,
        private readonly int                   $currentUserId,
        private readonly string                $currentUserFriendlyName,
        private readonly ElementQueryInterface $unassignedCountBaseQuery,
        private readonly ?string               $siteHandle,
        private readonly PluginSettings        $settings,
    ) {}

    /**
     * Define the "sources" that filter a list of elements in the CP when viewing what Craft calls
     * the "element index".
     *
     * @return array[]
     */
    public function defineSources(): array
    {
        $enabledContainerIds = $this->settings->getEnabledContainerIds($this->elementType);

        $unassignedCount = $this->unassignedCountBaseQuery
            ->{$this->containerIdQueryParam}($enabledContainerIds)
            ->site($this->siteHandle)
            ->isAssigned(false)
            ->count();

        $sources = [
            [
                'key' => VerificationStatus::Expired->handle(),
                'label' => VerificationStatus::Expired->label(),
                'criteria' => [
                    'isVerified' => false,
                    $this->containerIdQueryParam => $enabledContainerIds,
                ]
            ],
            [
                'key' => 'upcoming',
                'label' => Craft::t(Plugin::HANDLE, 'Imminent'),
                'criteria' => [
                    'isVerified' => true,
                    $this->containerIdQueryParam => $enabledContainerIds,
                    'verifiedUntil' => '< ' . DateHelper::imminentDateMax()->format('Y-m-d'),
                ],
            ],
            [
                'key' => VerificationStatus::Verified->handle(),
                'label' => VerificationStatus::Verified->label(),
                'criteria' => [
                    'isVerified' => true,
                    $this->containerIdQueryParam => $enabledContainerIds,
                ]
            ],
            [
                'key' => ReviewerStatus::Unassigned->handle(),
                'label' => ReviewerStatus::Unassigned->label(),
                'badgeCount' => $unassignedCount > 0 ? $unassignedCount : null,
                'criteria' => [
                    'isAssigned' => false,
                    $this->containerIdQueryParam => $enabledContainerIds,
                ],
            ],
            [
                'heading' => Craft::t(Plugin::HANDLE, 'Reviewer'),
            ],
            [
                'key' => 'mine',
                'label' => $this->currentUserFriendlyName,
                'criteria' => [
                    'reviewerId' => $this->currentUserId,
                    $this->containerIdQueryParam => $enabledContainerIds,
                ]
            ],
        ];

        foreach ($this->findReviewers() as $reviewer) {
            $sources[] = [
                'key' => 'reviewer-' . $reviewer->id,
                'label' => $reviewer->getFriendlyName(),
                'criteria' => [
                    'reviewerId' => $reviewer->id,
                    $this->containerIdQueryParam => $enabledContainerIds,
                ],
            ];
        }

        return $sources;
    }

    /**
     * Factory method for testing.
     *
     * @return User[]
     */
    protected function findReviewers(): array
    {
        return User::find()
            ->can(Permission::VerifyEntries->value)
            ->id(['not', $this->currentUserId])
            ->all();
    }
}
