<?php

use craft\elements\Entry as EntryElement;
use craft\helpers\Db;
use markhuot\craftpest\factories\Entry;
use webhubworks\verifiedentries\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedentries\db\PluginTable;

/**
 * INTEGRATION TESTS
 * @see VerifiableQueryBehavior Yii Behavior
 *
 * Tests that the query behavior's JOIN and filter methods work correctly against real database
 * rows. Verifies that site-scoped queries return only the correct site's verification data,
 * and that isVerified, isAssigned, and reviewerId filters include and exclude the right entries.
 */



// JOIN correctness
// =================================================================================================

it("returns the queried site's verification data when an entry exists on multiple sites", function () {
    $siteB = createSite('B');
    $section = createSection();
    $user = createUser();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;

    // Site A: no reviewer. Site B: reviewer assigned.
    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $primarySiteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2030-01-01 00:00:00',
    ]);

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $siteB->id,
        'reviewerId' => $user->id,
        'verifiedUntilDate' => '2030-01-01 00:00:00',
    ]);

    // A query scoped to site A should not see site B's reviewer
    $countOnSiteA = withVerifiableQueryBehavior(EntryElement::find()->siteId($primarySiteId))
        ->isAssigned(true)
        ->sectionId($section->id)
        ->count();

    // A query scoped to site B should see site B's reviewer
    $countOnSiteB = withVerifiableQueryBehavior(EntryElement::find()->siteId($siteB->id))
        ->isAssigned(true)
        ->sectionId($section->id)
        ->count();

    expect((int) $countOnSiteA)->toBe(0);
    expect((int) $countOnSiteB)->toBe(1);
});


// isVerified()
// =================================================================================================

it('excludes entries with expired dates when isVerified is true', function () {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2020-01-01 00:00:00',
    ]);

    $ids = withVerifiableQueryBehavior(EntryElement::find()->sectionId($section->id))
        ->isVerified(true)
        ->ids();

    expect($ids)->not->toContain($entry->getCanonicalId());
});

it('includes entries with future dates when isVerified is true', function () {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2099-01-01 00:00:00',
    ]);

    $ids = withVerifiableQueryBehavior(EntryElement::find()->sectionId($section->id))
        ->isVerified(true)
        ->ids();

    expect($ids)->toContain($entry->getCanonicalId());
});

it('includes entries with a null date when isVerified is true', function () {
    $section = createSection();
    $entry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entry->getCanonicalId(),
        'siteId' => $entry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => null,
    ]);

    $ids = withVerifiableQueryBehavior(EntryElement::find()->sectionId($section->id))
        ->isVerified(true)
        ->ids();

    expect($ids)->toContain($entry->getCanonicalId());
});

it('returns only entries with expired dates when isVerified is false', function () {
    $section = createSection();
    $expiredEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $verifiedEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $expiredEntry->getCanonicalId(),
        'siteId' => $expiredEntry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2020-01-01 00:00:00',
    ]);

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $verifiedEntry->getCanonicalId(),
        'siteId' => $verifiedEntry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2099-01-01 00:00:00',
    ]);

    $ids = withVerifiableQueryBehavior(EntryElement::find()->sectionId($section->id))
        ->isVerified(false)
        ->ids();

    expect($ids)->toContain($expiredEntry->getCanonicalId());
    expect($ids)->not->toContain($verifiedEntry->getCanonicalId());
});


// isAssigned()
// =================================================================================================

it('returns only entries with a reviewer when isAssigned is true', function () {
    $section = createSection();
    $reviewer = createUser();
    $assignedEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $unassignedEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $assignedEntry->getCanonicalId(),
        'siteId' => $assignedEntry->siteId,
        'reviewerId' => $reviewer->id,
        'verifiedUntilDate' => '2099-01-01 00:00:00',
    ]);

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $unassignedEntry->getCanonicalId(),
        'siteId' => $unassignedEntry->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2099-01-01 00:00:00',
    ]);

    $ids = withVerifiableQueryBehavior(EntryElement::find()->sectionId($section->id))
        ->isAssigned(true)
        ->ids();

    expect($ids)->toContain($assignedEntry->getCanonicalId());
    expect($ids)->not->toContain($unassignedEntry->getCanonicalId());
});

it('returns only entries without a reviewer and with a date set when isAssigned is false', function () {
    $section = createSection();
    $reviewer = createUser();
    $assignedEntry = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $unassignedWithDate = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $unassignedIndefinite = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $assignedEntry->getCanonicalId(),
        'siteId' => $assignedEntry->siteId,
        'reviewerId' => $reviewer->id,
        'verifiedUntilDate' => '2099-01-01 00:00:00',
    ]);

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $unassignedWithDate->getCanonicalId(),
        'siteId' => $unassignedWithDate->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => '2099-01-01 00:00:00',
    ]);

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $unassignedIndefinite->getCanonicalId(),
        'siteId' => $unassignedIndefinite->siteId,
        'reviewerId' => null,
        'verifiedUntilDate' => null,
    ]);

    $ids = withVerifiableQueryBehavior(EntryElement::find()->sectionId($section->id))
        ->isAssigned(false)
        ->ids();

    expect($ids)->toContain($unassignedWithDate->getCanonicalId());
    expect($ids)->not->toContain($assignedEntry->getCanonicalId());
    expect($ids)->not->toContain($unassignedIndefinite->getCanonicalId());
});


// reviewerId()
// =================================================================================================

it('returns only entries assigned to the given reviewer', function () {
    $section = createSection();
    $reviewerA = createUser('A');
    $reviewerB = createUser('B');
    $entryForA = withVerifiableBehavior(Entry::factory()->section($section)->create());
    $entryForB = withVerifiableBehavior(Entry::factory()->section($section)->create());

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entryForA->getCanonicalId(),
        'siteId' => $entryForA->siteId,
        'reviewerId' => $reviewerA->id,
        'verifiedUntilDate' => null,
    ]);

    Db::insert(PluginTable::ENTRIES, [
        'entryId' => $entryForB->getCanonicalId(),
        'siteId' => $entryForB->siteId,
        'reviewerId' => $reviewerB->id,
        'verifiedUntilDate' => null,
    ]);

    $ids = withVerifiableQueryBehavior(EntryElement::find()->sectionId($section->id))
        ->reviewerId($reviewerA->id)
        ->ids();

    expect($ids)->toContain($entryForA->getCanonicalId());
    expect($ids)->not->toContain($entryForB->getCanonicalId());
});
