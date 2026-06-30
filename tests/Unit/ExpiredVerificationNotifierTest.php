<?php

use webhubworks\verifiedelements\base\NotifiableInterface;
use webhubworks\verifiedelements\mail\ExpiredNotification;
use webhubworks\verifiedelements\services\ExpiredVerificationNotifier;

/**
 * UNIT TESTS
 * @see ExpiredVerificationNotifier Service
 *
 * Tests the orchestration logic that groups expired entries by reviewer and dispatches
 * notifications. The expired entry data and mail transport are both mocked, isolating the
 * grouping, recipient resolution, and notification dispatch decisions from any real database
 * or mail state.
 */


// hasExpiredEntriesByReviewer()
// =================================================================================================

it('returns false when there are no expired entries assigned to a reviewer', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed([], []);

    expect($notifier->hasExpiredEntriesByReviewer())->toBeFalse();
});

it('returns true when there are expired entries assigned to a reviewer', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed(
        [42 => [mockExpiredEntry(42)]],
        [],
    );

    expect($notifier->hasExpiredEntriesByReviewer())->toBeTrue();
});


// hasUnassignedExpiredEntries()
// =================================================================================================

it('returns false when there are no unassigned expired entries', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed([], []);

    expect($notifier->hasUnassignedExpiredEntries())->toBeFalse();
});

it('returns true when there are unassigned expired entries', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed(
        [],
        [mockExpiredEntry(null)],
    );

    expect($notifier->hasUnassignedExpiredEntries())->toBeTrue();
});


// reassignEntriesToUnassigned()
// =================================================================================================

it('returns false when the reviewer ID has no entries', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed([], []);

    expect($notifier->reassignEntriesToUnassigned(99))->toBeFalse();
});

it('returns true and moves entries to unassigned when reviewer is found', function () {
    $entry = mockExpiredEntry(42);
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed(
        [42 => [$entry]],
        [],
    );

    expect($notifier->reassignEntriesToUnassigned(42))->toBeTrue();
    expect($notifier->getUnassignedExpiredEntries())->toContain($entry);
});


// notifyRecipient()
// =================================================================================================

it('returns true when the notification is sent successfully', function () {
    $notification = Mockery::mock(ExpiredNotification::class);
    $notification->allows('send')->andReturn(true);

    $notifier = Mockery::mock(ExpiredVerificationNotifier::class, ['web'])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    /** @noinspection PhpMockeryInvalidMockingMethodInspection */
    $notifier->allows('buildExpiredNotification')->andReturn($notification);

    $recipient = Mockery::mock(NotifiableInterface::class);

    expect($notifier->notifyRecipient($recipient, []))->toBeTrue();
});

it('returns false when the notification fails to send', function () {
    $notification = Mockery::mock(ExpiredNotification::class);
    $notification->allows('send')->andReturn(false);

    $notifier = Mockery::mock(ExpiredVerificationNotifier::class, ['web'])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    /** @noinspection PhpMockeryInvalidMockingMethodInspection */
    $notifier->allows('buildExpiredNotification')->andReturn($notification);

    $recipient = Mockery::mock(NotifiableInterface::class);
    $recipient->allows('getEmail')->andReturn('reviewer@example.com');

    expect($notifier->notifyRecipient($recipient, []))->toBeFalse();
});
