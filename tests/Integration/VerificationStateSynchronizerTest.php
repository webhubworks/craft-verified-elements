<?php

use Carbon\Carbon;
use craft\elements\Entry as EntryElement;
use craft\elements\db\EntryQuery;
use markhuot\craftpest\factories\Entry;
use markhuot\craftpest\factories\Section;
use markhuot\craftpest\factories\User;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedentries\db\PluginQuery;
use webhubworks\verifiedentries\helpers\DateHelper;
use webhubworks\verifiedentries\services\VerificationStateSynchronizer;
use webhubworks\verifiedentries\services\singletons\PluginSettings;


// Helpers
// =================================================================================================

/**
 * Helper for ensuring entries have the behavior class that the plugin normally directs Craft to
 * attach to all entries.
 *
 * @param EntryElement $entry
 * @return EntryElement|VerifiableBehavior
 */
function withVerifiableBehavior(EntryElement $entry): EntryElement|VerifiableBehavior
{
    $entry->attachBehavior(VerifiableBehavior::NAME, VerifiableBehavior::class);

    return $entry;
}

/**
 * Helper for ensuring entry queries have the behavior class that the plugin normally directs
 * Craft to attach to all entries.
 *
 * @param EntryQuery $query
 * @return EntryQuery|VerifiableQueryBehavior
 */
function withVerifiableQueryBehavior(EntryQuery $query): EntryQuery|VerifiableQueryBehavior
{
    $query->attachBehavior(VerifiableQueryBehavior::NAME, VerifiableQueryBehavior::class);

    return $query;
}


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
