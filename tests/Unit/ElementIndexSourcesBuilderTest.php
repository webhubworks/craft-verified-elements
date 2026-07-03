<?php

use Carbon\Carbon;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\db\ElementQueryInterface;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use Mockery\MockInterface;
use webhubworks\verifiedelements\enums\ReviewerStatus;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\services\ElementIndexSourcesBuilder;
use webhubworks\verifiedelements\services\singletons\PluginSettings;

/**
 * UNIT TESTS
 * @see ElementIndexSourcesBuilder Service
 *
 * Tests the source definitions the plugin's element indexes are built from. All DB interaction is
 * mocked (settings, the unassigned-count query, the reviewers lookup), so these tests purely
 * verify the assembled source arrays: which sources exist, that every source is scoped by the
 * injected container query param, and how the unassigned badge and per-reviewer sources behave.
 */

/**
 * Wrapper class to bypass the DB-dependent reviewers lookup during unit testing.
 */
class TestableElementIndexSourcesBuilder extends ElementIndexSourcesBuilder
{
    public array $fakeReviewers = [];

    protected function findReviewers(): array
    {
        return $this->fakeReviewers;
    }
}

/**
 * Generate the builder with all of its DB touchpoints mocked.
 *
 * @param string $elementType
 * @param string $containerIdQueryParam
 * @param array $enabledContainerIds
 * @param int $unassignedCount
 * @param int $currentUserId
 * @param string $currentUserFriendlyName
 * @param string|null $siteHandle
 * @param User[] $reviewers Unsaved User elements returned by the reviewers lookup seam.
 * @return TestableElementIndexSourcesBuilder
 */
function makeSourcesBuilder(
    string  $elementType = Entry::class,
    string  $containerIdQueryParam = 'sectionId',
    array   $enabledContainerIds = [1, 2],
    int     $unassignedCount = 0,
    int     $currentUserId = 99,
    string  $currentUserFriendlyName = 'Current User',
    ?string $siteHandle = null,
    array   $reviewers = [],
): TestableElementIndexSourcesBuilder
{
    /** @var PluginSettings|MockInterface $settings */
    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getEnabledContainerIds')->with($elementType)->andReturn($enabledContainerIds);

    $queryClass = $elementType === Asset::class ? AssetQuery::class : EntryQuery::class;

    /** @var ElementQueryInterface|MockInterface $unassignedCountBaseQuery */
    $unassignedCountBaseQuery = Mockery::mock($queryClass);
    $unassignedCountBaseQuery->allows($containerIdQueryParam)->with($enabledContainerIds)->andReturnSelf();
    $unassignedCountBaseQuery->allows('site')->with($siteHandle)->andReturnSelf();
    $unassignedCountBaseQuery->allows('isAssigned')->with(false)->andReturnSelf();
    $unassignedCountBaseQuery->allows('count')->andReturn($unassignedCount);

    $builder = new TestableElementIndexSourcesBuilder(
        elementType: $elementType,
        containerIdQueryParam: $containerIdQueryParam,
        currentUserId: $currentUserId,
        currentUserFriendlyName: $currentUserFriendlyName,
        unassignedCountBaseQuery: $unassignedCountBaseQuery,
        siteHandle: $siteHandle,
        settings: $settings,
    );

    $builder->fakeReviewers = $reviewers;

    return $builder;
}

/**
 * Find a single source in the built sources list by its key.
 *
 * @param array $sources
 * @param string $key
 * @return array|null
 */
function findSourceByKey(array $sources, string $key): ?array
{
    foreach ($sources as $source) {
        if (($source['key'] ?? null) === $key) {
            return $source;
        }
    }

    return null;
}


// Status sources
// =================================================================================================

it('builds the expired, upcoming, verified, and unassigned sources', function () {
    $sources = makeSourcesBuilder()->defineSources();

    expect(findSourceByKey($sources, VerificationStatus::Expired->handle()))->not->toBeNull();
    expect(findSourceByKey($sources, 'upcoming'))->not->toBeNull();
    expect(findSourceByKey($sources, VerificationStatus::Verified->handle()))->not->toBeNull();
    expect(findSourceByKey($sources, ReviewerStatus::Unassigned->handle()))->not->toBeNull();
});

it('filters the expired and verified sources by verification state', function () {
    $sources = makeSourcesBuilder()->defineSources();

    $expired = findSourceByKey($sources, VerificationStatus::Expired->handle());
    $verified = findSourceByKey($sources, VerificationStatus::Verified->handle());

    expect($expired['criteria']['isVerified'])->toBeFalse();
    expect($verified['criteria']['isVerified'])->toBeTrue();
});

it('limits the upcoming source to the imminent window', function () {
    Carbon::setTestNow('2026-01-01 12:00:00');

    $sources = makeSourcesBuilder()->defineSources();
    $upcoming = findSourceByKey($sources, 'upcoming');

    expect($upcoming['criteria']['verifiedUntil'])->toBe('< 2026-01-31');

    Carbon::setTestNow();
});


// Container scoping (the element-agnostic part)
// =================================================================================================

it('scopes every source to the enabled sections for entries', function () {
    $sources = makeSourcesBuilder(
        elementType: Entry::class,
        containerIdQueryParam: 'sectionId',
        enabledContainerIds: [3, 4],
    )->defineSources();

    foreach ($sources as $source) {
        if (! isset($source['criteria'])) {
            continue;
        }

        expect($source['criteria']['sectionId'])->toBe([3, 4]);
    }
});

it('scopes every source to the enabled volumes for assets', function () {
    $sources = makeSourcesBuilder(
        elementType: Asset::class,
        containerIdQueryParam: 'volumeId',
        enabledContainerIds: [5],
    )->defineSources();

    foreach ($sources as $source) {
        if (! isset($source['criteria'])) {
            continue;
        }

        expect($source['criteria']['volumeId'])->toBe([5]);
        expect($source['criteria'])->not->toHaveKey('sectionId');
    }
});


// Unassigned badge count
// =================================================================================================

it('shows the unassigned badge count when unassigned elements exist', function () {
    $sources = makeSourcesBuilder(unassignedCount: 3)->defineSources();

    $unassigned = findSourceByKey($sources, ReviewerStatus::Unassigned->handle());

    expect($unassigned['badgeCount'])->toBe(3);
});

it('hides the unassigned badge count when everything is assigned', function () {
    $sources = makeSourcesBuilder(unassignedCount: 0)->defineSources();

    $unassigned = findSourceByKey($sources, ReviewerStatus::Unassigned->handle());

    expect($unassigned['badgeCount'])->toBeNull();
});


// Reviewer sources
// =================================================================================================

it("labels the 'mine' source with the current user's name and reviewer criteria", function () {
    $sources = makeSourcesBuilder(
        currentUserId: 42,
        currentUserFriendlyName: 'Ada',
    )->defineSources();

    $mine = findSourceByKey($sources, 'mine');

    expect($mine['label'])->toBe('Ada');
    expect($mine['criteria']['reviewerId'])->toBe(42);
});

it('appends one source per reviewer under the Reviewer heading', function () {
    $sources = makeSourcesBuilder(reviewers: [
        new User(['id' => 7, 'firstName' => 'Rita']),
        new User(['id' => 8, 'firstName' => 'Sam']),
    ])->defineSources();

    $headings = array_filter($sources, fn(array $source) => isset($source['heading']));
    $rita = findSourceByKey($sources, 'reviewer-7');
    $sam = findSourceByKey($sources, 'reviewer-8');

    expect($headings)->toHaveCount(1);
    expect($rita['label'])->toBe('Rita');
    expect($rita['criteria']['reviewerId'])->toBe(7);
    expect($sam['label'])->toBe('Sam');
    expect($sam['criteria']['reviewerId'])->toBe(8);
});
