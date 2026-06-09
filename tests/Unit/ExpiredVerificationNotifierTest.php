<?php

use webhubworks\verifiedentries\base\NotifiableInterface;
use webhubworks\verifiedentries\mail\ExpiredNotification;
use webhubworks\verifiedentries\models\ExpiredEntryData;
use webhubworks\verifiedentries\services\ExpiredVerificationNotifier;

// Test double that bypasses the DB-dependent setExpiredEntries()
class TestableExpiredVerificationNotifier extends ExpiredVerificationNotifier
{
    protected function setExpiredEntries(): void {}

    public function seed(array $byReviewer, array $unassigned): void
    {
        $reflection = new ReflectionClass(ExpiredVerificationNotifier::class);
        $reflection->getProperty('expiredEntriesByReviewerId')->setValue($this, $byReviewer);
        $reflection->getProperty('expiredUnassignedEntries')->setValue($this, $unassigned);
    }
}

function makeExpiredEntry(?int $reviewerId = 1): ExpiredEntryData
{
    return new ExpiredEntryData(
        100,
        1,
        $reviewerId,
        '2020-01-01 00:00:00',
        1,
        'Test Entry',
        'testSection',
        'default',
    );
}


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
        [42 => [makeExpiredEntry(42)]],
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
        [makeExpiredEntry(null)],
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
    $entry = makeExpiredEntry(42);
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
