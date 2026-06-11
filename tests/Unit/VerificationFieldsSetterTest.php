<?php

use Carbon\Carbon;
use webhubworks\verifiedentries\services\VerificationFieldsSetter;

/**
 * UNIT TESTS
 * @see VerificationFieldsSetter Service
 *
 * Tests the logic that resolves default reviewer and verification date values before an entry is
 * saved. All DB interaction is mocked, so these tests purely verify the decision-making rules:
 * when defaults apply, when they're skipped, and how edge cases like missing periods or existing
 * values are handled.
 */



// resolveReviewerId() tests
// =================================================================================================

it('returns null when section has no default reviewer', function () {
    $setter = mockVerificationFieldsSetter(
        null,
        new DateTime('+30 days'),
        true,
        null,
        'P30D',
    );

    expect($setter->resolveReviewerId())->toBeNull();
});

it('returns null when entry already has a reviewer', function () {
    $setter = mockVerificationFieldsSetter(
        5,
        new DateTime('+30 days'),
        true,
        10,
        'P30D',
    );

    expect($setter->resolveReviewerId())->toBeNull();
});

it('returns null when entry has no date and no date is about to be set', function () {
    $setter = mockVerificationFieldsSetter(
        null,
        null,
        false,
        10,
        null,
    );

    expect($setter->resolveReviewerId())->toBeNull();
});

it('returns default reviewer when entry has an existing date', function () {
    $setter = mockVerificationFieldsSetter(
        null,
        new DateTime('+30 days'),
        false,
        10,
        null,
    );

    expect($setter->resolveReviewerId())->toBe(10);
});

it('returns default reviewer on first save when date is about to be set', function () {
    $setter = mockVerificationFieldsSetter(
        null,
        null,
        true,
        10,
        'P30D',
    );

    expect($setter->resolveReviewerId())->toBe(10);
});


// resolveVerificationDate() tests
// =================================================================================================

it('returns null when not the first save', function () {
    $setter = mockVerificationFieldsSetter(
        null,
        null,
        false,
        null,
        'P30D',
    );

    expect($setter->resolveVerificationDate())->toBeNull();
});

it('returns null when entry already has a verification date', function () {
    $setter = mockVerificationFieldsSetter(
        null,
        new DateTime('+30 days'),
        true,
        null,
        'P30D',
    );

    expect($setter->resolveVerificationDate())->toBeNull();
});

it('returns null when section has no default period', function () {
    $setter = mockVerificationFieldsSetter(
        null,
        null,
        true,
        null,
        null,
    );

    expect($setter->resolveVerificationDate())->toBeNull();
});

it('returns null when the default period string is invalid', function () {
    $setter = mockVerificationFieldsSetter(
        null,
        null,
        true,
        null,
        'NOT_A_VALID_INTERVAL',
    );

    expect($setter->resolveVerificationDate())->toBeNull();
});

it('returns a date offset from now by the default period', function () {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $setter = mockVerificationFieldsSetter(
        null,
        null,
        true,
        null,
        'P30D',
    );

    $result = $setter->resolveVerificationDate();

    expect($result)->not->toBeNull();
    expect($result->format('Y-m-d'))->toBe('2026-01-31');

    Carbon::setTestNow();
});
