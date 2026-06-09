<?php

require_once __DIR__ . '/helpers.php';

use craft\elements\User;
use craft\models\Site;
use Mockery\MockInterface;
use webhubworks\verifiedentries\mail\ChangeNotification;
use webhubworks\verifiedentries\services\VerificationStateSynchronizer;

/**
 * UNIT TESTS
 * @see VerificationStateSynchronizer Service
 *
 * Tests the conditional logic that governs post-save behaviour — whether a section is enabled
 * for a site, whether a reviewer should be notified, and under what conditions notifications are
 * suppressed. All DB writes and mail sending are mocked; only the branching logic is under test.
 */



// isSectionEnabled()
// =================================================================================================

it('returns true when the section is enabled for the site', function () {
    $synchronizer = new VerificationStateSynchronizer(
        mockEntry(),
        mockPluginSettings(true),
        null,
    );

    expect($synchronizer->isSectionEnabled())->toBeTrue();
});

it('returns false when the section is not enabled for the site', function () {
    $synchronizer = new VerificationStateSynchronizer(
        mockEntry(),
        mockPluginSettings(false),
        null,
    );

    expect($synchronizer->isSectionEnabled())->toBeFalse();
});


// notifyReviewerOnChange()
// =================================================================================================

it('returns false when the entry has no verified until date', function () {
    $entry = mockEntry();
    $entry->enabled = true;
    $entry->allows('getHasVerifiedUntilDate')->andReturn(false);

    $synchronizer = new VerificationStateSynchronizer(
        $entry,
        mockPluginSettings(true),
        null,
    );

    expect($synchronizer->notifyReviewerOnChange())->toBeFalse();
});

it('returns false when the entry has no reviewer', function () {
    $site = Mockery::mock(Site::class);
    $site->allows('getName')->andReturn('Default');

    $entry = mockEntry();
    $entry->enabled = true;
    $entry->title = 'Test Entry';
    $entry->allows('getHasVerifiedUntilDate')->andReturn(true);
    $entry->allows('getCanonicalId')->andReturn(1);
    $entry->allows('getReviewer')->andReturn(null);
    $entry->allows('getSite')->andReturn($site);

    $synchronizer = new VerificationStateSynchronizer(
        $entry,
        mockPluginSettings(true),
        null,
    );

    expect($synchronizer->notifyReviewerOnChange())->toBeFalse();
});

it('returns false when the reviewer is the current editor', function () {
    $reviewer = Mockery::mock(User::class);
    $reviewer->id = 42;
    $reviewer->active = true;

    $entry = mockEntry();
    $entry->enabled = true;
    $entry->allows('getHasVerifiedUntilDate')->andReturn(true);
    $entry->allows('getReviewer')->andReturn($reviewer);

    $synchronizer = new VerificationStateSynchronizer(
        $entry,
        mockPluginSettings(true),
        42,
    );

    expect($synchronizer->notifyReviewerOnChange())->toBeFalse();
});

it('returns true when a change notification is sent to the reviewer', function () {
    $reviewer = Mockery::mock(User::class);
    $reviewer->id = 42;
    $reviewer->active = true;

    $notification = Mockery::mock(ChangeNotification::class);
    $notification->allows('send')->andReturn(true);

    $entry = mockEntry();
    $entry->enabled = true;
    $entry->allows('getHasVerifiedUntilDate')->andReturn(true);
    $entry->allows('getReviewer')->andReturn($reviewer);

    /** @var VerificationStateSynchronizer|MockInterface $synchronizer */
    $synchronizer = Mockery::mock(
        VerificationStateSynchronizer::class,
        [$entry, mockPluginSettings(true), 99]
    )
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $synchronizer->allows('buildChangeNotification')->andReturn($notification);

    expect($synchronizer->notifyReviewerOnChange())->toBeTrue();
});
