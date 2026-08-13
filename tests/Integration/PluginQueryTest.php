<?php

use craft\helpers\Db;
use markhuot\craftpest\factories\Asset;
use markhuot\craftpest\factories\Entry;
use markhuot\craftpest\factories\Volume;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\db\PluginTable;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\models\ElementData;

/**
 * INTEGRATION TESTS
 * @see PluginQuery
 *
 * Tests that the expired-verification digest query (which feeds the reminder emails) returns
 * only entries that are actually live. Disabled entries — globally or for the queried site —
 * must be excluded, matching the per-user verification list (WBHB-9618).
 *
 * Also tests the mixed-type queries: entries and assets combined via UNION ALL must not leak
 * across types, must hydrate ElementData correctly per type, must order and paginate across
 * the whole union, and must apply the optional reviewer filter to every union arm.
 */

/**
 * Every site ID - the plugin's in-scope set on a multi-site edition. Passed to the PluginQuery
 * builders so these tests exercise the query logic without edition site-scoping getting in the way.
 *
 * @return int[]
 */
function allSiteIds(): array
{
    return \Craft::$app->getSites()->getAllSiteIds();
}


// expiredVerifiableEntries()
// =================================================================================================

it('excludes globally disabled entries but keeps enabled ones in the expired set', function() {
    $section = createSection();
    $enabledEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $disabledEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    // The query INNER JOINs on an enabled section-settings row, so create one for this site.
    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $enabledEntry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);

    // Both entries are past their verification date.
    foreach ([$enabledEntry, $disabledEntry] as $entry) {
        Db::insert(PluginTable::ATTRIBUTES, [
            'elementId' => $entry->getCanonicalId(),
            'siteId' => $entry->siteId,
            'reviewerId' => null,
            'verifiedUntilDate' => '2020-01-01 00:00:00',
        ]);
    }

    // Disable one entry globally, as an editor would via the CP lightswitch.
    Db::update('{{%elements}}', ['enabled' => false], ['id' => $disabledEntry->getCanonicalId()]);

    $expiredIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries(allSiteIds())->all()
    );

    expect($expiredIds)->toContain($enabledEntry->getCanonicalId());
    expect($expiredIds)->not->toContain($disabledEntry->getCanonicalId());
});

it('excludes rows whose site is not in the in-scope set', function() {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2020-01-01 00:00:00',
    ]);

    // In scope: the entry's own site is returned.
    $inScopeIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries([$entry->siteId])->all()
    );
    expect($inScopeIds)->toContain($entry->getCanonicalId());

    // Out of scope: a site outside the set is excluded, even though the row and section are enabled.
    $outOfScopeIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries([$entry->siteId + 1000])->all()
    );
    expect($outOfScopeIds)->not->toContain($entry->getCanonicalId());
});

it('excludes entries that are disabled for the queried site', function() {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2020-01-01 00:00:00',
    ]);

    // Globally enabled, but disabled for this site (the multisite case).
    Db::update(
        '{{%elements_sites}}',
        ['enabled' => false],
        ['elementId' => $entry->getCanonicalId(), 'siteId' => $entry->siteId]
    );

    $expiredIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries(allSiteIds())->all()
    );

    expect($expiredIds)->not->toContain($entry->getCanonicalId());
});

it('returns rows that hydrate ElementData with the correct identity and edit URL', function() {
    $section = createSection();
    $reviewer = getSharedReviewer();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => $reviewer->id,
        'verifiedUntilDate' => '2020-01-01 00:00:00',
    ]);

    $rows = array_filter(
        PluginQuery::expiredVerifiableEntries(allSiteIds())->all(),
        static fn($row) => (int) $row['id'] === $entry->getCanonicalId()
    );

    expect($rows)->toHaveCount(1);

    $elementData = ElementData::fromArray(array_values($rows)[0]);

    expect($elementData->type)->toBe(\craft\elements\Entry::class);
    expect($elementData->id)->toBe($entry->getCanonicalId());
    expect($elementData->reviewerId)->toBe($reviewer->id);
    expect($elementData->containerId)->toBe($section->id);
    expect($elementData->containerHandle)->toBe($section->handle);

    // The URL embeds the element id followed by the slug. A broken row contract (the old
    // "entryId" key bug) would produce ".../0-slug" here.
    expect($elementData->cpEditUrl)->toContain(
        sprintf('entries/%s/%s-', $section->handle, $entry->getCanonicalId())
    );
});


// Mixed-type queries (UNION ALL)
// =================================================================================================

it('returns expired entries and assets together without cross-type leakage', function() {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $volume = Volume::factory()->create();
    $asset = withVerifiableBehavior(Asset::factory()->volume($volume->handle)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);
    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $volume->id,
        'siteId' => $asset->siteId,
        'elementType' => \craft\elements\Asset::class,
        'enabled' => true,
    ]);

    foreach ([$entry, $asset] as $element) {
        Db::insert(PluginTable::ATTRIBUTES, [
            'elementId' => $element->getCanonicalId(),
            'siteId' => $element->siteId,
            'reviewerId' => null,
            'verifiedUntilDate' => '2020-01-01 00:00:00',
        ]);
    }

    $mixedRows = PluginQuery::expiredVerifiableElements([
        \craft\elements\Entry::class,
        \craft\elements\Asset::class,
    ], allSiteIds())->all();

    $mixedIds = array_map(static fn($row) => (int) $row['id'], $mixedRows);
    expect($mixedIds)->toContain($entry->getCanonicalId());
    expect($mixedIds)->toContain($asset->getCanonicalId());

    // The per-type queries must not leak the other type's rows.
    $entryOnlyIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries(allSiteIds())->all()
    );
    expect($entryOnlyIds)->toContain($entry->getCanonicalId());
    expect($entryOnlyIds)->not->toContain($asset->getCanonicalId());

    $assetOnlyIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableAssets(allSiteIds())->all()
    );
    expect($assetOnlyIds)->toContain($asset->getCanonicalId());
    expect($assetOnlyIds)->not->toContain($entry->getCanonicalId());
});

it('hydrates an expired asset row into ElementData with volume identity and asset edit URL', function() {
    $volume = Volume::factory()->create();
    $asset = withVerifiableBehavior(Asset::factory()->volume($volume->handle)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $volume->id,
        'siteId' => $asset->siteId,
        'elementType' => \craft\elements\Asset::class,
        'enabled' => true,
    ]);
    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $asset->getCanonicalId(),
        'siteId' => $asset->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2020-01-01 00:00:00',
    ]);

    $rows = array_filter(
        PluginQuery::expiredVerifiableAssets(allSiteIds())->all(),
        static fn($row) => (int) $row['id'] === $asset->getCanonicalId()
    );

    expect($rows)->toHaveCount(1);

    $elementData = ElementData::fromArray(array_values($rows)[0]);

    expect($elementData->type)->toBe(\craft\elements\Asset::class);
    expect($elementData->containerId)->toBe($volume->id);
    expect($elementData->containerHandle)->toBe($volume->handle);
    expect($elementData->cpEditUrl)->toContain(
        sprintf('assets/edit/%s', $asset->getCanonicalId())
    );
});

it('excludes an expired entry when the only matching container row belongs to another element type', function() {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    // A container row whose ID matches the section, but registered for ASSETS. Section and
    // volume IDs share the containers table and can collide - the type-blind join regression.
    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Asset::class,
        'enabled' => true,
    ]);
    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2020-01-01 00:00:00',
    ]);

    $expiredIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries(allSiteIds())->all()
    );

    expect($expiredIds)->not->toContain($entry->getCanonicalId());
});

it('limits expired elements to the given reviewer across the union', function() {
    $reviewer = getSharedReviewer();
    $otherReviewer = getSharedReviewer('b');

    $section = createSection();
    $assignedEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $otherReviewerEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $unassignedEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $volume = Volume::factory()->create();
    $assignedAsset = withVerifiableBehavior(Asset::factory()->volume($volume->handle)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $assignedEntry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);
    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $volume->id,
        'siteId' => $assignedAsset->siteId,
        'elementType' => \craft\elements\Asset::class,
        'enabled' => true,
    ]);

    $reviewerIdsByElement = [
        [$assignedEntry, $reviewer->id],
        [$otherReviewerEntry, $otherReviewer->id],
        [$unassignedEntry, null],
        [$assignedAsset, $reviewer->id],
    ];
    foreach ($reviewerIdsByElement as [$element, $reviewerId]) {
        Db::insert(PluginTable::ATTRIBUTES, [
            'elementId' => $element->getCanonicalId(),
            'siteId' => $element->siteId,
            'reviewerId' => $reviewerId,
            'verifiedUntilDate' => '2020-01-01 00:00:00',
        ]);
    }

    $elementTypes = [
        \craft\elements\Entry::class,
        \craft\elements\Asset::class,
    ];

    $filteredIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableElements($elementTypes, allSiteIds(), $reviewer->id)->all()
    );

    expect($filteredIds)->toContain($assignedEntry->getCanonicalId());
    expect($filteredIds)->toContain($assignedAsset->getCanonicalId());
    expect($filteredIds)->not->toContain($otherReviewerEntry->getCanonicalId());
    expect($filteredIds)->not->toContain($unassignedEntry->getCanonicalId());

    // Without the filter, the same fixtures all come back - proving the exclusions above are
    // the reviewer condition's doing, not an accident of the fixtures.
    $unfilteredIds = array_map(
        static fn($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableElements($elementTypes, allSiteIds())->all()
    );

    expect($unfilteredIds)->toContain($otherReviewerEntry->getCanonicalId());
    expect($unfilteredIds)->toContain($unassignedEntry->getCanonicalId());
});

it('orders and paginates across the reviewer union, not per subquery', function() {
    $reviewer = getSharedReviewer('b');

    $section = createSection();
    $entry = withVerifiableBehavior(
        Entry::factory()->section($section)->title('AAA union sort entry')->create()
    );

    $volume = Volume::factory()->create();
    $asset = withVerifiableBehavior(
        Asset::factory()->volume($volume->handle)->title('ZZZ union sort asset')->create()
    );

    foreach ([$entry, $asset] as $element) {
        Db::insert(PluginTable::ATTRIBUTES, [
            'elementId' => $element->getCanonicalId(),
            'siteId' => $element->siteId,
            'reviewerId' => $reviewer->id,
            'verifiedUntilDate' => '2030-01-01 00:00:00',
        ]);
    }

    $query = PluginQuery::elementsByReviewer($reviewer->id, [
        \craft\elements\Entry::class,
        \craft\elements\Asset::class,
    ], allSiteIds())->orderBy(['title' => SORT_ASC]);

    expect((int) $query->count())->toBe(2);

    // Page 1: the entry (AAA) sorts first even though assets are the second union arm.
    $firstPage = $query->limit(1)->offset(0)->all();
    expect($firstPage)->toHaveCount(1);
    expect((int) $firstPage[0]['id'])->toBe($entry->getCanonicalId());

    // Page 2: the asset (ZZZ).
    $secondPage = $query->limit(1)->offset(1)->all();
    expect($secondPage)->toHaveCount(1);
    expect((int) $secondPage[0]['id'])->toBe($asset->getCanonicalId());
});


// assignedReviewerIds()
// =================================================================================================

it('returns each assigned reviewer once and ignores unassigned rows', function() {
    $section = createSection();
    $reviewer = getSharedReviewer();
    $entryOne = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entryTwo = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $unassignedEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entryOne->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);

    // Two assignments for the same reviewer must collapse to one ID.
    foreach ([$entryOne, $entryTwo] as $entry) {
        Db::insert(PluginTable::ATTRIBUTES, [
            'elementId' => $entry->getCanonicalId(),
            'siteId' => $entry->siteId,
            'reviewerId' => $reviewer->id,
            'verifiedUntilDate' => null,
        ]);
    }

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $unassignedEntry->getCanonicalId(),
        'siteId' => $unassignedEntry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => null,
    ]);

    $reviewerIds = array_map(
        'intval',
        PluginQuery::assignedReviewerIds(ElementType::Entry, allSiteIds())->column()
    );

    expect($reviewerIds)->toBe([$reviewer->id]);
});

it('does not leak reviewers across element types', function() {
    $section = createSection();
    $volume = Volume::factory()->create();
    $entryReviewer = getSharedReviewer('a');
    $assetReviewer = getSharedReviewer('b');
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $asset = withVerifiableBehavior(Asset::factory()->volume($volume->handle)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $volume->id,
        'siteId' => $asset->siteId,
        'elementType' => \craft\elements\Asset::class,
        'enabled' => true,
    ]);

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => $entryReviewer->id,
        'verifiedUntilDate' => null,
    ]);

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $asset->getCanonicalId(),
        'siteId' => $asset->siteId,
        'reviewerId' => $assetReviewer->id,
        'verifiedUntilDate' => null,
    ]);

    $entryReviewerIds = array_map(
        'intval',
        PluginQuery::assignedReviewerIds(ElementType::Entry, allSiteIds())->column()
    );
    $assetReviewerIds = array_map(
        'intval',
        PluginQuery::assignedReviewerIds(ElementType::Asset, allSiteIds())->column()
    );

    expect($entryReviewerIds)->toBe([$entryReviewer->id]);
    expect($assetReviewerIds)->toBe([$assetReviewer->id]);
});

it('excludes reviewers whose assignments sit only in disabled containers', function() {
    $section = createSection();
    $reviewer = getSharedReviewer();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => false,
    ]);

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => $reviewer->id,
        'verifiedUntilDate' => null,
    ]);

    expect(PluginQuery::assignedReviewerIds(ElementType::Entry, allSiteIds())->column())->toBe([]);
});

it('requires the enabled container row to match the element type', function() {
    $section = createSection();
    $reviewer = getSharedReviewer();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    // containerId has no FK, so a row can exist for the same ID under the WRONG type - the
    // section/volume ID-collision scenario. It must not satisfy the Entry-type query.
    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Asset::class,
        'enabled' => true,
    ]);

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => $reviewer->id,
        'verifiedUntilDate' => null,
    ]);

    expect(PluginQuery::assignedReviewerIds(ElementType::Entry, allSiteIds())->column())->toBe([]);
});

it('excludes assignments on out-of-scope sites', function() {
    $section = createSection();
    $reviewer = getSharedReviewer();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::CONTAINERS, [
        'containerId' => $section->id,
        'siteId' => $entry->siteId,
        'elementType' => \craft\elements\Entry::class,
        'enabled' => true,
    ]);

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => $reviewer->id,
        'verifiedUntilDate' => null,
    ]);

    // In scope: the entry's own site.
    $inScopeReviewerIds = array_map(
        'intval',
        PluginQuery::assignedReviewerIds(ElementType::Entry, [$entry->siteId])->column()
    );
    expect($inScopeReviewerIds)->toBe([$reviewer->id]);

    // Out of scope: every other site.
    $otherSiteIds = array_values(array_diff(allSiteIds(), [$entry->siteId]));
    expect(PluginQuery::assignedReviewerIds(ElementType::Entry, $otherSiteIds)->column())->toBe([]);
});
