<?php

use craft\helpers\Db;
use markhuot\craftpest\factories\Asset;
use markhuot\craftpest\factories\Entry;
use markhuot\craftpest\factories\Volume;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\db\PluginTable;
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


// expiredVerifiableEntries()
// =================================================================================================

it('excludes globally disabled entries but keeps enabled ones in the expired set', function () {
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
        static fn ($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries()->all()
    );

    expect($expiredIds)->toContain($enabledEntry->getCanonicalId());
    expect($expiredIds)->not->toContain($disabledEntry->getCanonicalId());
});

it('excludes entries that are disabled for the queried site', function () {
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
        static fn ($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries()->all()
    );

    expect($expiredIds)->not->toContain($entry->getCanonicalId());
});

it('returns rows that hydrate ElementData with the correct identity and edit URL', function () {
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
        PluginQuery::expiredVerifiableEntries()->all(),
        static fn ($row) => (int) $row['id'] === $entry->getCanonicalId()
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

it('returns expired entries and assets together without cross-type leakage', function () {
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
    ])->all();

    $mixedIds = array_map(static fn ($row) => (int) $row['id'], $mixedRows);
    expect($mixedIds)->toContain($entry->getCanonicalId());
    expect($mixedIds)->toContain($asset->getCanonicalId());

    // The per-type queries must not leak the other type's rows.
    $entryOnlyIds = array_map(
        static fn ($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries()->all()
    );
    expect($entryOnlyIds)->toContain($entry->getCanonicalId());
    expect($entryOnlyIds)->not->toContain($asset->getCanonicalId());

    $assetOnlyIds = array_map(
        static fn ($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableAssets()->all()
    );
    expect($assetOnlyIds)->toContain($asset->getCanonicalId());
    expect($assetOnlyIds)->not->toContain($entry->getCanonicalId());
});

it('hydrates an expired asset row into ElementData with volume identity and asset edit URL', function () {
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
        PluginQuery::expiredVerifiableAssets()->all(),
        static fn ($row) => (int) $row['id'] === $asset->getCanonicalId()
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

it('excludes an expired entry when the only matching container row belongs to another element type', function () {
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
        static fn ($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableEntries()->all()
    );

    expect($expiredIds)->not->toContain($entry->getCanonicalId());
});

it('limits expired elements to the given reviewer across the union', function () {
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
        static fn ($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableElements($elementTypes, $reviewer->id)->all()
    );

    expect($filteredIds)->toContain($assignedEntry->getCanonicalId());
    expect($filteredIds)->toContain($assignedAsset->getCanonicalId());
    expect($filteredIds)->not->toContain($otherReviewerEntry->getCanonicalId());
    expect($filteredIds)->not->toContain($unassignedEntry->getCanonicalId());

    // Without the filter, the same fixtures all come back - proving the exclusions above are
    // the reviewer condition's doing, not an accident of the fixtures.
    $unfilteredIds = array_map(
        static fn ($row) => (int) $row['id'],
        PluginQuery::expiredVerifiableElements($elementTypes)->all()
    );

    expect($unfilteredIds)->toContain($otherReviewerEntry->getCanonicalId());
    expect($unfilteredIds)->toContain($unassignedEntry->getCanonicalId());
});

it('orders and paginates across the reviewer union, not per subquery', function () {
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
    ])->orderBy(['title' => SORT_ASC]);

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
