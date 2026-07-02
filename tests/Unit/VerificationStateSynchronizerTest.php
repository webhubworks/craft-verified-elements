<?php

use craft\elements\User;
use Mockery\MockInterface;
use webhubworks\verifiedelements\mail\ChangeNotification;
use webhubworks\verifiedelements\services\VerificationStateSynchronizer;

/**
 * UNIT TESTS
 * @see VerificationStateSynchronizer Service
 *
 * Tests the conditional logic that governs post-save behaviour - whether a reviewer should be
 * notified and under what conditions notifications are suppressed. All DB writes and mail sending
 * are mocked; only the branching logic is under test.
 */



// notifyReviewerOnChange()
// =================================================================================================

it('returns false when the element has no verified until date', function () {
    $synchronizer = new VerificationStateSynchronizer(
        mockElementData(),
        [],
        true,
        mockPluginSettings(true),
        null,
    );

    expect($synchronizer->notifyReviewerOnChange())->toBeFalse();
});

it('returns false when the element is disabled', function () {
    $synchronizer = new VerificationStateSynchronizer(
        mockElementData(verifiedUntilDate: '2030-01-01 00:00:00'),
        [],
        false,
        mockPluginSettings(true),
        null,
    );

    expect($synchronizer->notifyReviewerOnChange())->toBeFalse();
});

it('returns false when the element has no reviewer', function () {
    $synchronizer = new VerificationStateSynchronizer(
        mockElementData(reviewerId: null, verifiedUntilDate: '2030-01-01 00:00:00'),
        [],
        true,
        mockPluginSettings(true),
        null,
    );

    expect($synchronizer->notifyReviewerOnChange())->toBeFalse();
});

it('returns false when the reviewer is the current editor', function () {
    $reviewer = Mockery::mock(User::class);
    $reviewer->id = 42;
    $reviewer->active = true;

    /** @var VerificationStateSynchronizer|MockInterface $synchronizer */
    $synchronizer = Mockery::mock(
        VerificationStateSynchronizer::class,
        [
            mockElementData(reviewerId: 42, verifiedUntilDate: '2030-01-01 00:00:00'),
            [],
            true,
            mockPluginSettings(true),
            42,
        ]
    )
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $synchronizer->allows('findReviewer')->andReturn($reviewer);

    expect($synchronizer->notifyReviewerOnChange())->toBeFalse();
});

it('returns true when a change notification is sent to the reviewer', function () {
    $reviewer = Mockery::mock(User::class);
    $reviewer->id = 42;
    $reviewer->active = true;

    $notification = Mockery::mock(ChangeNotification::class);
    $notification->allows('send')->andReturn(true);

    /** @var VerificationStateSynchronizer|MockInterface $synchronizer */
    $synchronizer = Mockery::mock(
        VerificationStateSynchronizer::class,
        [
            mockElementData(reviewerId: 42, verifiedUntilDate: '2030-01-01 00:00:00'),
            [],
            true,
            mockPluginSettings(true),
            99,
        ]
    )
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $synchronizer->allows('findReviewer')->andReturn($reviewer);
    $synchronizer->allows('buildChangeNotification')->andReturn($notification);

    expect($synchronizer->notifyReviewerOnChange())->toBeTrue();
});
