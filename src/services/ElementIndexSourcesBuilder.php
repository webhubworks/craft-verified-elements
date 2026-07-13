<?php

namespace webhubworks\verifiedelements\services;

use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\elements\VerifiedAsset;
use webhubworks\verifiedelements\elements\VerifiedEntry;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\Permission;
use webhubworks\verifiedelements\enums\ReviewerStatus;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\Plugin;
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
    ) {
    }

    /**
     * Define the "sources" that filter a list of elements in the CP when viewing what Craft calls
     * the "element index".
     *
     * @return array[]
     */
    public function defineSources(): array
    {
        $enabledContainerIds = $this->settings->getEnabledContainerIds($this->elementType);

        // Restrict each source (and so the index's site menu) to the sites this edition may
        // surface. Craft narrows the menu to the current source's sites, intersected with the
        // user's editable sites - so without multi-site, only the primary site is reachable.
        $inScopeSiteIds = $this->settings->getInScopeSiteIds();

        // The number of unassigned elements whose "Verified until" dates aren't "Indefinite".
        // The user needs to be prompted to assign these entries to someone to review them.
        $expiringUnassignedCount = $this->unassignedCountBaseQuery
            ->{$this->containerIdQueryParam}($enabledContainerIds)
            ->site($this->siteHandle)
            ->isAssigned(false)
            ->verifiedUntilDate('not :empty:')
            ->count();

        $sources = [
            [
                'key' => VerificationStatus::Expired->handle(),
                'label' => VerificationStatus::Expired->label(),
                'sites' => $inScopeSiteIds,
                'criteria' => [
                    'isVerified' => false,
                    $this->containerIdQueryParam => $enabledContainerIds,
                ],
            ],
            [
                'key' => 'upcoming',
                'label' => Craft::t(Plugin::HANDLE, 'Imminent'),
                'sites' => $inScopeSiteIds,
                'criteria' => [
                    'isVerified' => true,
                    $this->containerIdQueryParam => $enabledContainerIds,
                    'verifiedUntil' => '< ' . DateHelper::imminentDateMax()->format('Y-m-d'),
                ],
            ],
            [
                'key' => VerificationStatus::Verified->handle(),
                'label' => VerificationStatus::Verified->label(),
                'sites' => $inScopeSiteIds,
                'criteria' => [
                    'isVerified' => true,
                    $this->containerIdQueryParam => $enabledContainerIds,
                ],
            ],
            [
                'key' => ReviewerStatus::Unassigned->handle(),
                'label' => ReviewerStatus::Unassigned->label(),
                'sites' => $inScopeSiteIds,
                'badgeCount' => $expiringUnassignedCount > 0 ? $expiringUnassignedCount : null,
                'badgeLabel' => Craft::t(Plugin::HANDLE, 'Number of unassigned entries that will expire.'),
                'data' => ['badge-title' => Craft::t(Plugin::HANDLE, 'Number of unassigned entries that will expire.')],
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
                'sites' => $inScopeSiteIds,
                'criteria' => [
                    'reviewerId' => $this->currentUserId,
                    $this->containerIdQueryParam => $enabledContainerIds,
                ],
            ],
        ];

        foreach ($this->findReviewers() as $reviewer) {
            $sources[] = [
                'key' => 'reviewer-' . $reviewer->id,
                'label' => $reviewer->getFriendlyName(),
                'sites' => $inScopeSiteIds,
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
     * Returns the users who actually have review assignments for this element type, NOT the
     * holders of a verify permission. Assignments outlive permission changes, so a permission
     * query misrepresents the real reviewer set.
     *
     * @return User[]
     */
    protected function findReviewers(): array
    {
        $reviewerIds = PluginQuery::assignedReviewerIds(
            ElementType::from($this->elementType),
            $this->settings->getInScopeSiteIds()
        )->column();

        // The "mine" source already covers the current user.
        $reviewerIds = array_filter(
            array_map('intval', $reviewerIds),
            fn(int $reviewerId): bool => $reviewerId !== $this->currentUserId
        );

        if (empty($reviewerIds)) {
            return [];
        }

        return User::find()
            ->id($reviewerIds)
            ->all();
    }
}
