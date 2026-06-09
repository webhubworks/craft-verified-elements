<?php

use Carbon\Carbon;
use Mockery\MockInterface;
use webhubworks\verifiedentries\models\SectionDefaults;
use webhubworks\verifiedentries\services\VerificationFieldsSetter;
use webhubworks\verifiedentries\services\singletons\PluginSettings;

function mockSettings(?int $reviewerId, ?string $period): PluginSettings
{
    $defaults = $reviewerId !== null || $period !== null
        ? new SectionDefaults(1, 'Test', 'test', 1, $reviewerId, $period)
        : null;

    /** @var PluginSettings|MockInterface $settings */
    $settings = Mockery::mock(PluginSettings::class);
    $settings->allows('getDefaultSettingsForSection')->andReturn($defaults);

    return $settings;
}

function makeSetter(
    ?int      $currentReviewerId,
    ?DateTime $currentVerifiedUntilDate,
    bool      $isFirstSave,
    ?int      $defaultReviewerId,
    ?string   $defaultPeriod,
): VerificationFieldsSetter
{
    return new VerificationFieldsSetter(
        1,
        1,
        $currentReviewerId,
        $currentVerifiedUntilDate,
        $isFirstSave,
        mockSettings($defaultReviewerId, $defaultPeriod),
    );
}


// resolveReviewerId() tests
// =================================================================================================

it('returns null when section has no default reviewer', function () {
    $setter = makeSetter(
        null,
        new DateTime('+30 days'),
        true,
        null,
        'P30D',
    );

    expect($setter->resolveReviewerId())->toBeNull();
});

it('returns null when entry already has a reviewer', function () {
    $setter = makeSetter(
        5,
        new DateTime('+30 days'),
        true,
        10,
        'P30D',
    );

    expect($setter->resolveReviewerId())->toBeNull();
});

it('returns null when entry has no date and no date is about to be set', function () {
    $setter = makeSetter(
        null,
        null,
        false,
        10,
        null,
    );

    expect($setter->resolveReviewerId())->toBeNull();
});

it('returns default reviewer when entry has an existing date', function () {
    $setter = makeSetter(
        null,
        new DateTime('+30 days'),
        false,
        10,
        null,
    );

    expect($setter->resolveReviewerId())->toBe(10);
});

it('returns default reviewer on first save when date is about to be set', function () {
    $setter = makeSetter(
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
    $setter = makeSetter(
        null,
        null,
        false,
        null,
        'P30D',
    );

    expect($setter->resolveVerificationDate())->toBeNull();
});

it('returns null when entry already has a verification date', function () {
    $setter = makeSetter(
        null,
        new DateTime('+30 days'),
        true,
        null,
        'P30D',
    );

    expect($setter->resolveVerificationDate())->toBeNull();
});

it('returns null when section has no default period', function () {
    $setter = makeSetter(
        null,
        null,
        true,
        null,
        null,
    );

    expect($setter->resolveVerificationDate())->toBeNull();
});

it('returns null when the default period string is invalid', function () {
    $setter = makeSetter(
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

    $setter = makeSetter(
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
