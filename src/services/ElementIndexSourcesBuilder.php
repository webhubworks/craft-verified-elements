<?php

namespace webhubworks\verifiedelements\services;

use Craft;
use craft\elements\db\ElementQueryInterface;
use craft\elements\User;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\elements\VerifiedAsset;
use webhubworks\verifiedelements\elements\VerifiedEntry;
use webhubworks\verifiedelements\enums\ElementType;
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
    private readonly string $containerIdQueryParam;

    public function __construct(
        private readonly string                $elementType,
        private readonly int                   $currentUserId,
        private readonly string                $currentUserName,
        private readonly ElementQueryInterface $unassignedCountBaseQuery,
        private readonly ?string               $siteHandle,
        private readonly PluginSettings        $settings,
    ) {
        $this->containerIdQueryParam = ElementType::from($elementType)->containerIdColumn();
    }

    /**
     * Define the "sources" that filter a list of elements in the CP when viewing what Craft calls
     * the "element index" (the table that lists entries, for example).
     *
     * @return array[]
     */
    public function defineSources(): array
    {
        // "Containers" are sections (for entries), volumes (for assets), etc.
        $containerIds = $this->settings->getEnabledContainerIds($this->elementType);

        // Restrict each source (and so the index's site menu) to the sites this edition may
        // surface. Craft narrows the menu to the current source's sites, intersected with the
        // user's editable sites - so without multi-site, only the primary site is reachable.
        $siteIds = $this->settings->getInScopeSiteIds();

        $sources = [
            $this->expiredSource($containerIds, $siteIds),
            $this->imminentSource($containerIds, $siteIds),
            $this->verifiedSource($containerIds, $siteIds),
            $this->unassignedSource($containerIds, $siteIds),
            ['heading' => Craft::t(Plugin::HANDLE, 'Reviewer')],
            $this->currentUserSource($containerIds, $siteIds),
        ];

        foreach ($this->findReviewers() as $reviewer) {
            $sources[] = $this->reviewerSource($containerIds, $siteIds, $reviewer);
        }

        return $sources;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * The "Expired" elements source filter for the elements index table.
     *
     * @param array $containerIds section IDs (for entries), volume IDs (for assets), etc.
     * @param array $siteIds
     * @return array The source array item Craft uses to add this filter to the element index page.
     */
    protected function expiredSource(array $containerIds, array $siteIds): array
    {
        return [
            'key' => VerificationStatus::Expired->handle(),
            'label' => VerificationStatus::Expired->label(),
            'sites' => $siteIds,
            'criteria' => [
                'isVerified' => false,
                $this->containerIdQueryParam => $containerIds,
            ],
        ];
    }

    /**
     * The "Imminent" elements source filter for the elements index table.
     *
     * @param array $containerIds section IDs (for entries), volume IDs (for assets), etc.
     * @param array $siteIds
     * @return array The source array item Craft uses to add this filter to the element index page.
     */
    protected function imminentSource(array $containerIds, array $siteIds): array
    {
        return [
            'key' => 'upcoming',
            'label' => Craft::t(Plugin::HANDLE, 'Imminent'),
            'sites' => $siteIds,
            'criteria' => [
                'isVerified' => true,
                $this->containerIdQueryParam => $containerIds,
                'verifiedUntil' => '< ' . DateHelper::imminentDateMax()->format('Y-m-d'),
            ],
        ];
    }

    /**
     * The "Verified" elements source filter for the elements index table.
     *
     * @param array $containerIds section IDs (for entries), volume IDs (for assets), etc.
     * @param array $siteIds
     * @return array The source array item Craft uses to add this filter to the element index page.
     */
    protected function verifiedSource(array $containerIds, array $siteIds): array
    {
        return [
            'key' => VerificationStatus::Verified->handle(),
            'label' => VerificationStatus::Verified->label(),
            'sites' => $siteIds,
            'criteria' => [
                'isVerified' => true,
                $this->containerIdQueryParam => $containerIds,
            ],
        ];
    }

    /**
     * The "Unassigned" elements source filter for the elements index table.
     *
     * @param array $containerIds section IDs (for entries), volume IDs (for assets), etc.
     * @param array $siteIds
     * @return array The source array item Craft uses to add this filter to the element index page.
     */
    protected function unassignedSource(array $containerIds, array $siteIds): array
    {
        // The number of unassigned elements whose "Verified until" dates aren't "Indefinite".
        // The user needs to be prompted to assign these entries to someone to review them.
        $expiringUnassignedCount = $this->unassignedCountBaseQuery
            ->{$this->containerIdQueryParam}($containerIds)
            ->site($this->siteHandle)
            ->isAssigned(false)
            ->verifiedUntilDate('not :empty:')
            ->count();

        $badgeLabel = Craft::t(Plugin::HANDLE, 'Number of unassigned elements that will expire.');

        return [
            'key' => ReviewerStatus::Unassigned->handle(),
            'label' => ReviewerStatus::Unassigned->label(),
            'sites' => $siteIds,
            'badgeCount' => $expiringUnassignedCount > 0 ? $expiringUnassignedCount : null,
            'badgeLabel' => $badgeLabel,
            'data' => ['badge-title' => $badgeLabel],
            'criteria' => [
                'isAssigned' => false,
                $this->containerIdQueryParam => $containerIds,
            ],
        ];
    }

    /**
     * The source filter for the elements index table that filters elements by those assigned to
     * the currently logged-in user for review.
     *
     * @param array $containerIds section IDs (for entries), volume IDs (for assets), etc.
     * @param array $siteIds
     * @return array The source array item Craft uses to add this filter to the element index page.
     */
    protected function currentUserSource(array $containerIds, array $siteIds): array
    {
        return [
            'key' => 'mine',
            'label' => $this->currentUserName,
            'sites' => $siteIds,
            'criteria' => [
                'reviewerId' => $this->currentUserId,
                $this->containerIdQueryParam => $containerIds,
            ],
        ];
    }

    /**
     * The source filter for the elements index table that filters elements by those assigned to
     * a given user (the Reviewer).
     *
     * @param array $containerIds section IDs (for entries), volume IDs (for assets), etc.
     * @param array $siteIds
     * @param User $reviewer
     * @return array The source array item Craft uses to add this filter to the element index page.
     */
    protected function reviewerSource(array $containerIds, array $siteIds, User $reviewer): array
    {
        return [
            'key' => 'reviewer-' . $reviewer->id,
            'label' => $reviewer->getFriendlyName(),
            'sites' => $siteIds,
            'criteria' => [
                'reviewerId' => $reviewer->id,
                $this->containerIdQueryParam => $containerIds,
            ],
        ];
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
