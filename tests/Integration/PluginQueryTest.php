<?php

use craft\helpers\Db;
use markhuot\craftpest\factories\Entry;
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
