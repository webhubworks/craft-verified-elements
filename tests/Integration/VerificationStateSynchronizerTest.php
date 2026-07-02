<?php

use Carbon\Carbon;
use craft\helpers\Db;
use markhuot\craftpest\factories\Entry;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\db\PluginTable;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\models\ElementData;
use webhubworks\verifiedelements\models\SectionDefaults;
use webhubworks\verifiedelements\services\VerificationStateSynchronizer;
use webhubworks\verifiedelements\services\singletons\PluginSettings;

/**
 * INTEGRATION TESTS
 * @see VerificationStateSynchronizer Service
 *
 * Tests that the synchronizer's DB write methods produce the correct rows in
 * verifiedelements_attributes against a real Craft database. Covers upsert correctness,
 * multisite record seeding, and propagation - the scenarios where mocking the DB layer would
 * hide real schema and query bugs.
 *
 * Note: `ElementData::fromElement()` snapshots the element at construction time, so every
 * snapshot is taken AFTER the element's fields are set, and tests that change fields between
 * saves must build a fresh synchronizer from a fresh snapshot.
 */



// saveVerificationRecord()
// =================================================================================================

it('writes a verification record for the element and site', function () {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entry->setVerifiedUntilDate(Carbon::now()->addDays(30));

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [],
        true,
        $settings,
        null,
    );
    $synchronizer->saveVerificationRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->one();

    expect($row)->not->toBeNull();
    expect($row['elementId'])->toBe($entry->getCanonicalId());
    expect($row['siteId'])->toBe($entry->siteId);
});

it('upserts rather than inserts a second row when called twice for the same element and site', function () {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entry->setVerifiedUntilDate(Carbon::now()->addDays(30));

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [],
        true,
        $settings,
        null,
    );

    $synchronizer->saveVerificationRecord();

    // Calling this again updates the existing row rather than inserting a new one.
    // If the upsert wasn't working correctly, count() would return 2 instead of 1.
    $synchronizer->saveVerificationRecord();

    $count = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->count();

    expect((int)$count)->toBe(1);
});

it('overwrites the stored values when saved again from a fresh snapshot with updated fields', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entry->setVerifiedUntilDate(Carbon::now()->addDays(30));

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [],
        true,
        $settings,
        null,
    );
    $synchronizer->saveVerificationRecord();

    // ElementData is an immutable snapshot, so saving the updated date requires a fresh
    // snapshot and a fresh synchronizer - mirroring a second save event in production.
    $entry->setVerifiedUntilDate(Carbon::now()->addDays(90));
    $secondSynchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [],
        true,
        $settings,
        null,
    );
    $secondSynchronizer->saveVerificationRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->one();
    $storedDate = DateHelper::toDateTime($row['verifiedUntilDate']);

    expect($storedDate)->not->toBeNull();
    expect($storedDate->format('Y-m-d'))->toBe('2026-04-01');

    Carbon::setTestNow();
});

it('stores a null reviewer id when none is set on the element', function () {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [],
        true,
        $settings,
        null,
    );
    $synchronizer->saveVerificationRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->one();

    expect($row['reviewerId'])->toBeNull();
});

it('stores the reviewer id when one is set on the element', function () {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $reviewer = getSharedReviewer();
    $entry->setReviewerId($reviewer->id);

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [],
        true,
        $settings,
        null,
    );
    $synchronizer->saveVerificationRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->one();

    expect((int) $row['reviewerId'])->toBe($reviewer->id);
});


// ensureOtherSiteRecords()
// =================================================================================================

it('creates a record for each other supported site', function () {
    $siteB = createSite('B');
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getDefaultSettingsForSection')->andReturn(null);

    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [$entry->siteId, $siteB->id],
        true,
        $settings,
        null,
    );
    $synchronizer->ensureOtherSiteRecords();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();

    expect($row)->not->toBeNull();
});

it("seeds each site record using that site's own section defaults", function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $siteB = createSite('B');
    $section = createSection();
    $reviewer = getSharedReviewer();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $sectionDefaults = new SectionDefaults(
        $section->id,
        $section->name,
        $section->handle,
        $siteB->id,
        $reviewer->id,
        'P90D',
    );

    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getDefaultSettingsForSection')->andReturn($sectionDefaults);

    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [$entry->siteId, $siteB->id],
        true,
        $settings,
        null,
    );
    $synchronizer->ensureOtherSiteRecords();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();
    $storedDate = DateHelper::toDateTime($row['verifiedUntilDate']);

    expect((int) $row['reviewerId'])->toBe($reviewer->id);
    expect($storedDate->format('Y-m-d'))->toBe('2026-04-01');

    Carbon::setTestNow();
});

it('does not overwrite an existing record for another site', function () {
    $siteB = createSite('B');
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $siteB->id,
        'reviewerId' => null,
        'verifiedUntilDate' => '2030-06-01 00:00:00',
    ]);

    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getDefaultSettingsForSection')->andReturn(null);

    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [$entry->siteId, $siteB->id],
        true,
        $settings,
        null,
    );
    $synchronizer->ensureOtherSiteRecords();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();

    expect($row['verifiedUntilDate'])->toBe('2030-06-01 00:00:00');
});

it('returns true when all site records are seeded successfully', function () {
    $siteB = createSite('B');
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getDefaultSettingsForSection')->andReturn(null);

    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [$entry->siteId, $siteB->id],
        true,
        $settings,
        null,
    );

    expect($synchronizer->ensureOtherSiteRecords())->toBeTrue();
});


// ensurePropagatedRecord()
// =================================================================================================

it('creates no record when no source record exists for the element', function () {
    $siteB = createSite('B');
    $section = createSection();
    $entryOnSiteA = withVerifiableBehavior(Entry::factory()->section($section)->create());

    // Simulate the entry being propagated to site B by setting its siteId to site B
    $entryOnSiteB = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entryOnSiteB->siteId = $siteB->id;

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entryOnSiteB),
        [],
        true,
        $settings,
        null,
    );
    $synchronizer->ensurePropagatedRecord();

    $row = PluginQuery::verifiableEntry($entryOnSiteB->getCanonicalId(), $siteB->id)->one();

    expect($row)->toBeNull();
});

it('copies the source record to the propagated site when none exists there yet', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $siteB = createSite('B');
    $section = createSection();
    $reviewer = getSharedReviewer();

    // Create an entry and seed its site A record
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => $reviewer->id,
        'verifiedUntilDate' => '2026-04-01 00:00:00',
    ]);

    // Simulate the entry being propagated to site B (the snapshot is taken after the switch)
    $entry->siteId = $siteB->id;

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [],
        true,
        $settings,
        null,
    );
    $synchronizer->ensurePropagatedRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();
    $storedDate = DateHelper::toDateTime($row['verifiedUntilDate']);

    expect($row)->not->toBeNull();
    expect((int) $row['reviewerId'])->toBe($reviewer->id);
    expect($storedDate->format('Y-m-d'))->toBe('2026-04-01');

    Carbon::setTestNow();
});

it('does not overwrite an existing record on the propagated site', function () {
    $siteB = createSite('B');
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    // Seed site A record
    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2026-04-01 00:00:00',
    ]);

    // Seed an independently-set site B record
    Db::insert(PluginTable::ATTRIBUTES, [
        'elementId' => $entry->getCanonicalId(),
        'siteId' => $siteB->id,
        'reviewerId' => null,
        'verifiedUntilDate' => '2030-01-01 00:00:00',
    ]);

    // Simulate propagation to site B (the snapshot is taken after the switch)
    $entry->siteId = $siteB->id;

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer(
        ElementData::fromElement($entry),
        [],
        true,
        $settings,
        null,
    );
    $synchronizer->ensurePropagatedRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();

    expect($row['verifiedUntilDate'])->toBe('2030-01-01 00:00:00');
});
