<?php

use Carbon\Carbon;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\i18n\Formatter;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\models\ElementData;

/**
 * UNIT TESTS
 * @see ElementData
 *
 * Covers hydration + type casting from raw query rows, derived verification state, per-type CP
 * URL building, and the JSON display shape consumed by the reviewer dashboard's Admin Table.
 */

/**
 * Builds a raw dashboard/expired query row. Values are strings to mirror what the DB layer
 * returns, so the (int) casts in fromArray() are exercised.
 *
 * @param array $overrides
 * @return array
 */
function elementDataRow(array $overrides = []): array
{
    return array_merge([
        'type' => Entry::class,
        'rowId' => '5',
        'id' => '128',
        'siteId' => '1',
        'reviewerId' => '1',
        'verifiedUntilDate' => '2027-06-23 22:00:00',
        'containerId' => '1',
        'title' => 'Test 2 - EN',
        'slug' => 'test-2-en',
        'dateUpdated' => '2026-06-24 12:00:00',
        'containerName' => 'Test Entries',
        'containerHandle' => 'testEntries',
        'siteHandle' => 'sandboxEn',
        'siteName' => 'Sandbox EN',
    ], $overrides);
}


// fromArray()
// =================================================================================================

it('casts id-like columns to int and keeps strings as strings', function() {
    $elementData = ElementData::fromArray(elementDataRow());

    expect($elementData->rowId)->toBe(5)
        ->and($elementData->id)->toBe(128)
        ->and($elementData->siteId)->toBe(1)
        ->and($elementData->reviewerId)->toBe(1)
        ->and($elementData->containerId)->toBe(1)
        ->and($elementData->type)->toBe(Entry::class)
        ->and($elementData->verifiedUntilDate)->toBe('2027-06-23 22:00:00')
        ->and($elementData->title)->toBe('Test 2 - EN')
        ->and($elementData->siteHandle)->toBe('sandboxEn');
});

it('hydrates null row id, reviewer, and "Verified until" date', function() {
    $elementData = ElementData::fromArray(elementDataRow([
        'rowId' => null,
        'reviewerId' => null,
        'verifiedUntilDate' => null,
    ]));

    expect($elementData->rowId)->toBeNull()
        ->and($elementData->reviewerId)->toBeNull()
        ->and($elementData->verifiedUntilDate)->toBeNull();
});

it('hydrates a null title as an empty string', function() {
    $elementData = ElementData::fromArray(elementDataRow(['title' => null]));

    expect($elementData->title)->toBe('');
});


// isVerified()
// =================================================================================================

it('is verified when the "Verified until" date is in the future', function() {
    Carbon::setTestNow(Carbon::create(2026, 6, 24, 0, 0, 0, 'UTC'));

    try {
        $elementData = ElementData::fromArray(elementDataRow([
            'verifiedUntilDate' => '2027-06-23 22:00:00',
        ]));

        expect($elementData->isVerified())->toBeTrue();
    } finally {
        Carbon::setTestNow();
    }
});

it('is not verified when the "Verified until" date is in the past', function() {
    Carbon::setTestNow(Carbon::create(2026, 6, 24, 0, 0, 0, 'UTC'));

    try {
        $elementData = ElementData::fromArray(elementDataRow([
            'verifiedUntilDate' => '2026-06-15 22:00:00',
        ]));

        expect($elementData->isVerified())->toBeFalse();
    } finally {
        Carbon::setTestNow();
    }
});

it('is verified when the "Verified until" date is null (Indefinitely)', function() {
    // Null means "Indefinitely" and counts as verified, matching VerificationStatus::fromDate()
    // and the rest of the CP. The pre-refactor dashboard transform treated null as expired;
    // that discrepancy was resolved deliberately (see WBHB-9773 notes, 2026-07-02).
    $elementData = ElementData::fromArray(elementDataRow([
        'verifiedUntilDate' => null,
    ]));

    expect($elementData->isVerified())->toBeTrue();
});


// getReadableVerifiedUntilDate()
// =================================================================================================

it('formats set and null "Verified until" dates through the shared date helper', function() {
    // The widget template and jsonSerialize() both rely on this delegation; the helper's own
    // formatting rules (Today / n days / short date / Indefinite) are DateHelper's contract.
    $row = elementDataRow();
    $withDate = ElementData::fromArray($row);
    $indefinite = ElementData::fromArray(elementDataRow(['verifiedUntilDate' => null]));

    expect($withDate->getReadableVerifiedUntilDate())
        ->toBe(DateHelper::readableVerificationDate(DateHelper::toDateTime($row['verifiedUntilDate'])))
        ->and($indefinite->getReadableVerifiedUntilDate())
        ->toBe(DateHelper::readableVerificationDate(null));
});


// cpEditUrl (via fromArray)
// =================================================================================================

it('builds an entry CP edit URL scoped to the element site', function() {
    $elementData = ElementData::fromArray(elementDataRow());

    expect($elementData->cpEditUrl)
        ->toContain('entries/testEntries/128-test-2-en')
        ->toContain('site=sandboxEn');
});

it('builds an asset CP edit URL scoped to the element site', function() {
    $elementData = ElementData::fromArray(elementDataRow(['type' => Asset::class]));

    expect($elementData->cpEditUrl)
        ->toContain('assets/edit/128')
        ->toContain('site=sandboxEn');
});

it('builds an empty CP edit URL for an unsupported element type', function() {
    $elementData = ElementData::fromArray(elementDataRow(['type' => 'craft\elements\Category']));

    expect($elementData->cpEditUrl)->toBe('');
});


// jsonSerialize()
// =================================================================================================

it('serializes to the display shape the Admin Table expects', function() {
    Carbon::setTestNow(Carbon::create(2026, 6, 24, 0, 0, 0, 'UTC'));

    try {
        $row = elementDataRow();
        $elementData = ElementData::fromArray($row);

        $json = $elementData->jsonSerialize();

        expect($json)->toHaveKeys([
            'type', 'rowId', 'id', 'title', 'siteId', 'siteName', 'siteHandle',
            'containerId', 'containerName', 'containerHandle', 'dateUpdated',
            'reviewerId', 'verifiedUntilDate', 'isVerified', 'url',
        ]);

        // `slug` is a transient URL-building input, never a serialized field, and the link key
        // is `url` (what the Vue Admin Table reads), not the property name.
        expect($json)->not->toHaveKey('slug')
            ->and($json)->not->toHaveKey('cpEditUrl');

        // id-like fields serialize as ints, not numeric strings
        expect($json['rowId'])->toBe(5)
            ->and($json['id'])->toBe(128)
            ->and($json['reviewerId'])->toBe(1);

        // derived / display fields
        expect($json['isVerified'])->toBe('Verified')
            ->and($json['url'])->toBe($elementData->cpEditUrl)
            ->and($json['verifiedUntilDate'])
                ->toBe(DateHelper::readableVerificationDate(DateHelper::toDateTime($row['verifiedUntilDate'])))
            ->and($json['dateUpdated'])
                ->toBe((new Formatter())->asDate(DateHelper::toDateTime($row['dateUpdated'])));

        // straight passthrough
        expect($json['containerName'])->toBe('Test Entries')
            ->and($json['siteName'])->toBe('Sandbox EN');
    } finally {
        Carbon::setTestNow();
    }
});

it('labels a null (Indefinitely) date as Verified', function() {
    $row = elementDataRow(['verifiedUntilDate' => null]);

    $json = ElementData::fromArray($row)->jsonSerialize();

    expect($json['isVerified'])->toBe('Verified')
        ->and($json['verifiedUntilDate'])->toBe(DateHelper::readableVerificationDate(null));
});
