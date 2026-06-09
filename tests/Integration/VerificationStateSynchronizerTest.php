<?php

require_once __DIR__ . '/../helpers.php';

use Carbon\Carbon;
use craft\helpers\Db;
use markhuot\craftpest\factories\Entry;
use markhuot\craftpest\factories\Section;
use markhuot\craftpest\factories\User;
use webhubworks\verifiedentries\db\PluginQuery;
use webhubworks\verifiedentries\db\PluginTable;
use webhubworks\verifiedentries\helpers\DateHelper;
use webhubworks\verifiedentries\models\SectionDefaults;
use webhubworks\verifiedentries\services\VerificationStateSynchronizer;
use webhubworks\verifiedentries\services\singletons\PluginSettings;


// saveVerificationRecord()
// =================================================================================================

it('writes a verification record for the entry and site', function () {
    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entry->setVerifiedUntilDate(Carbon::now()->addDays(30));

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->saveVerificationRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->one();

    expect($row)->not->toBeNull();
    expect($row['entryId'])->toBe($entry->getCanonicalId());
    expect($row['siteId'])->toBe($entry->siteId);
});

it('upserts rather than inserts a second row when called twice for the same entry and site', function () {
    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entry->setVerifiedUntilDate(Carbon::now()->addDays(30));

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);

    $synchronizer->saveVerificationRecord();
    $synchronizer->saveVerificationRecord();

    $count = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->count();

    expect((int)$count)->toBe(1);
});

it('overwrites the stored values when called a second time with updated fields', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entry->setVerifiedUntilDate(Carbon::now()->addDays(30));

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->saveVerificationRecord();

    $entry->setVerifiedUntilDate(Carbon::now()->addDays(90));
    $synchronizer->saveVerificationRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->one();
    $storedDate = DateHelper::toDateTime($row['verifiedUntilDate']);

    expect($storedDate)->not->toBeNull();
    expect($storedDate->format('Y-m-d'))->toBe('2026-04-01');

    Carbon::setTestNow();
});

it('stores a null reviewer id when none is set on the entry', function () {
    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->saveVerificationRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->one();

    expect($row['reviewerId'])->toBeNull();
});

it('stores the reviewer id when one is set on the entry', function () {
    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $reviewer = User::factory()->create();
    $entry->setReviewerId($reviewer->id);

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->saveVerificationRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $entry->siteId)->one();

    expect((int) $row['reviewerId'])->toBe($reviewer->id);
});


// ensureOtherSiteRecords()
// =================================================================================================

it('creates a record for each other supported site', function () {
    $siteB = createSite('Site B', 'siteB');
    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getDefaultSettingsForSection')->andReturn(null);

    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->ensureOtherSiteRecords();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();

    expect($row)->not->toBeNull();
});

it("seeds each site record using that site's own section defaults", function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $siteB = createSite('Site B', 'siteB');
    $section = Section::factory()->create();
    $reviewer = User::factory()->create();
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

    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->ensureOtherSiteRecords();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();
    $storedDate = DateHelper::toDateTime($row['verifiedUntilDate']);

    expect((int) $row['reviewerId'])->toBe($reviewer->id);
    expect($storedDate->format('Y-m-d'))->toBe('2026-04-01');

    Carbon::setTestNow();
});

it('does not overwrite an existing record for another site', function () {
    $siteB = createSite('Site B', 'siteB');
    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $siteB->id,
        'reviewerId' => null,
        'verifiedUntilDate' => '2030-06-01 00:00:00',
    ]);

    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getDefaultSettingsForSection')->andReturn(null);

    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->ensureOtherSiteRecords();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();

    expect($row['verifiedUntilDate'])->toBe('2030-06-01 00:00:00');
});

it('returns true when all site records are seeded successfully', function () {
    $siteB = createSite('Site B', 'siteB');
    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getDefaultSettingsForSection')->andReturn(null);

    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);

    expect($synchronizer->ensureOtherSiteRecords())->toBeTrue();
});


// ensurePropagatedRecord()
// =================================================================================================

it('creates no record when no source record exists for the entry', function () {
    $siteB = createSite('Site B', 'siteB');
    $section = Section::factory()->create();
    $entryOnSiteA = withVerifiableBehavior(Entry::factory()->section($section)->create());

    // Simulate the entry being propagated to site B by setting its siteId to site B
    $entryOnSiteB = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entryOnSiteB->siteId = $siteB->id;

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer($entryOnSiteB, $settings, null);
    $synchronizer->ensurePropagatedRecord();

    $row = PluginQuery::verifiableEntry($entryOnSiteB->getCanonicalId(), $siteB->id)->one();

    expect($row)->toBeNull();
});

it('copies the source record to the propagated site when none exists there yet', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $siteB = createSite('Site B', 'siteB');
    $section = Section::factory()->create();
    $reviewer = User::factory()->create();

    // Create an entry and seed its site A record
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => $reviewer->id,
        'verifiedUntilDate' => '2026-04-01 00:00:00',
    ]);

    // Simulate the entry being propagated to site B
    $entry->siteId = $siteB->id;

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->ensurePropagatedRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();
    $storedDate = DateHelper::toDateTime($row['verifiedUntilDate']);

    expect($row)->not->toBeNull();
    expect((int) $row['reviewerId'])->toBe($reviewer->id);
    expect($storedDate->format('Y-m-d'))->toBe('2026-04-01');

    Carbon::setTestNow();
});

it('does not overwrite an existing record on the propagated site', function () {
    $siteB = createSite('Site B', 'siteB');
    $section = Section::factory()->create();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    // Seed site A record
    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2026-04-01 00:00:00',
    ]);

    // Seed an independently-set site B record
    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $siteB->id,
        'reviewerId' => null,
        'verifiedUntilDate' => '2030-01-01 00:00:00',
    ]);

    // Simulate propagation to site B
    $entry->siteId = $siteB->id;

    $settings = Mockery::mock(PluginSettings::class);
    $synchronizer = new VerificationStateSynchronizer($entry, $settings, null);
    $synchronizer->ensurePropagatedRecord();

    $row = PluginQuery::verifiableEntry($entry->getCanonicalId(), $siteB->id)->one();

    expect($row['verifiedUntilDate'])->toBe('2030-01-01 00:00:00');
});
