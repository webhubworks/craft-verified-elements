<?php

use Carbon\Carbon;
use craft\elements\Entry;
use webhubworks\verifiedelements\enums\DateStatus;
use webhubworks\verifiedelements\services\VerificationFieldsRenderer;

/**
 * UNIT TESTS
 * @see VerificationFieldsRenderer::dateSelectOptions()
 *
 * Tests the logic that builds the "Verified until" dropdown options. The container defaults are
 * mocked (via mockPluginSettings), so these tests purely verify the option-assembly rules: the
 * four preset periods, marking the container's default period, prepending the currently selected
 * date, de-duplicating a preset that equals that date, and the trailing "Indefinitely" option.
 *
 * `Carbon::setTestNow()` pins "now" so the computed preset dates are deterministic; the afterEach
 * hook resets it even when an assertion fails mid-test.
 */

afterEach(function() {
    Carbon::setTestNow();
});


it('returns the four preset periods plus an indefinite option', function() {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $options = VerificationFieldsRenderer::dateSelectOptions(
        1,
        1,
        Entry::class,
        mockPluginSettings(true, null, null),
        null,
    );

    expect($options)->toHaveCount(5);
    expect(array_column($options, 'value'))->toBe([
        '2026-01-08', // + 7 days
        '2026-01-31', // + 30 days
        '2026-04-01', // + 90 days
        '2027-01-01', // + 1 year
        false,        // Indefinitely
    ]);
});

it('always ends with an indefinite option whose value is false', function() {
    $options = VerificationFieldsRenderer::dateSelectOptions(
        1,
        1,
        Entry::class,
        mockPluginSettings(),
        null,
    );

    $lastOption = end($options);

    expect($lastOption['value'])->toBeFalse();
    expect($lastOption['label'])->toBe(DateStatus::Indefinite->label());
});

it('marks the option matching the container default period', function() {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $options = VerificationFieldsRenderer::dateSelectOptions(
        1,
        1,
        Entry::class,
        mockPluginSettings(true, null, 'P30D'),
        null,
    );

    // Collect each preset's hint keyed by its date value.
    $hintsByValue = [];
    foreach ($options as $option) {
        if (isset($option['data']['hint'])) {
            $hintsByValue[$option['value']] = $option['data']['hint'];
        }
    }

    // The + 30 day option (2026-01-31) is the container default, so its hint carries the marker.
    expect($hintsByValue['2026-01-31'])->toContain('(')->toEndWith(')');

    // No other preset carries the default marker.
    foreach ($hintsByValue as $value => $hint) {
        if ($value === '2026-01-31') {
            continue;
        }
        expect($hint)->not->toContain('(');
    }
});

it('prepends the currently selected date as the first option', function() {
    Carbon::setTestNow('2026-01-01 00:00:00');

    $options = VerificationFieldsRenderer::dateSelectOptions(
        1,
        1,
        Entry::class,
        mockPluginSettings(),
        new DateTime('2026-06-15'),
    );

    // Currently selected date + 4 presets + indefinite.
    expect($options)->toHaveCount(6);
    expect($options[0]['value'])->toBe('2026-06-15');
    // The prepended option is a bare label/value pair, with no duration hint.
    expect($options[0])->not->toHaveKey('data');
});

it('does not duplicate a preset that equals the currently selected date', function() {
    Carbon::setTestNow('2026-01-01 00:00:00');

    // 2026-01-08 is exactly the + 7 day preset.
    $options = VerificationFieldsRenderer::dateSelectOptions(
        1,
        1,
        Entry::class,
        mockPluginSettings(),
        new DateTime('2026-01-08'),
    );

    $values = array_column($options, 'value');

    // Currently selected date + 3 remaining presets + indefinite (the + 7 day preset is skipped).
    expect($options)->toHaveCount(5);
    expect($options[0]['value'])->toBe('2026-01-08');
    expect(array_filter($values, fn($value) => $value === '2026-01-08'))->toHaveCount(1);
});
