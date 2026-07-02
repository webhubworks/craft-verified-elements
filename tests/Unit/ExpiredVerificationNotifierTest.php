<?php

use webhubworks\verifiedelements\base\NotifiableInterface;
use webhubworks\verifiedelements\mail\ExpiredNotification;
use webhubworks\verifiedelements\services\ExpiredVerificationNotifier;

/**
 * UNIT TESTS
 * @see ExpiredVerificationNotifier Service
 *
 * Tests the orchestration logic that groups expired elements by reviewer and dispatches
 * notifications. The expired element data and mail transport are both mocked, isolating the
 * grouping, recipient resolution, and notification dispatch decisions from any real database
 * or mail state.
 */


// hasExpiredElementsByReviewer()
// =================================================================================================

it('returns false when there are no expired elements assigned to a reviewer', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed([], []);

    expect($notifier->hasExpiredElementsByReviewer())->toBeFalse();
});

it('returns true when there are expired elements assigned to a reviewer', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed(
        [42 => [mockElementData(reviewerId: 42, verifiedUntilDate: '2020-01-01 00:00:00')]],
        [],
    );

    expect($notifier->hasExpiredElementsByReviewer())->toBeTrue();
});


// hasUnassignedExpiredElements()
// =================================================================================================

it('returns false when there are no unassigned expired elements', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed([], []);

    expect($notifier->hasUnassignedExpiredElements())->toBeFalse();
});

it('returns true when there are unassigned expired elements', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed(
        [],
        [mockElementData(reviewerId: null, verifiedUntilDate: '2020-01-01 00:00:00')],
    );

    expect($notifier->hasUnassignedExpiredElements())->toBeTrue();
});


// reassignElementsToUnassigned()
// =================================================================================================

it('returns false when the reviewer ID has no elements', function () {
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed([], []);

    expect($notifier->reassignElementsToUnassigned(99))->toBeFalse();
});

it('returns true and moves elements to unassigned when reviewer is found', function () {
    $elementData = mockElementData(reviewerId: 42, verifiedUntilDate: '2020-01-01 00:00:00');
    $notifier = new TestableExpiredVerificationNotifier('web');
    $notifier->seed(
        [42 => [$elementData]],
        [],
    );

    expect($notifier->reassignElementsToUnassigned(42))->toBeTrue();
    expect($notifier->getUnassignedExpiredElements())->toContain($elementData);
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
