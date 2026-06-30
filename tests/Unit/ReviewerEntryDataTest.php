<?php

use Carbon\Carbon;
use craft\i18n\Formatter;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\models\ReviewerEntryData;

/**
 * UNIT TESTS
 * @see ReviewerEntryData
 *
 * Covers hydration + type casting, derived verification state, CP URL building, and the JSON
 * display shape consumed by the reviewer dashboard's Admin Table.
 */

/**
 * Builds a raw reviewer-entry query row. Values are strings to mirror what the DB layer returns,
 * so the (int) casts in fromArray() are exercised.
 *
 * @param array $overrides
 * @return array
 */
function reviewerEntryRow(array $overrides = []): array
{
    return array_merge([
        'id' => '5',
        'entryId' => '128',
        'siteId' => '1',
        'reviewerId' => '1',
        'verifiedUntilDate' => '2027-06-23 22:00:00',
        'sectionId' => '1',
        'title' => 'Test 2 - EN',
        'slug' => 'test-2-en',
        'dateUpdated' => '2026-06-24 12:00:00',
        'sectionName' => 'Test Entries',
        'sectionHandle' => 'testEntries',
        'siteHandle' => 'sandboxEn',
        'siteName' => 'Sandbox EN',
    ], $overrides);
}


// fromArray()
// =================================================================================================

it('casts id-like columns to int and keeps strings as strings', function () {
    $entry = ReviewerEntryData::fromArray(reviewerEntryRow());

    expect($entry->id)->toBe(5)
        ->and($entry->entryId)->toBe(128)
        ->and($entry->siteId)->toBe(1)
        ->and($entry->reviewerId)->toBe(1)
        ->and($entry->sectionId)->toBe(1)
        ->and($entry->verifiedUntilDate)->toBe('2027-06-23 22:00:00')
        ->and($entry->title)->toBe('Test 2 - EN')
        ->and($entry->siteHandle)->toBe('sandboxEn');
});

it('hydrates a null reviewer and a null "Verified until" date', function () {
    $entry = ReviewerEntryData::fromArray(reviewerEntryRow([
        'reviewerId' => null,
        'verifiedUntilDate' => null,
    ]));

    expect($entry->reviewerId)->toBeNull()
        ->and($entry->verifiedUntilDate)->toBeNull();
});


// isVerified()
// =================================================================================================

it('is verified when the "Verified until" date is in the future', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 24, 0, 0, 0, 'UTC'));

    try {
        $entry = ReviewerEntryData::fromArray(reviewerEntryRow([
            'verifiedUntilDate' => '2027-06-23 22:00:00',
        ]));

        expect($entry->isVerified())->toBeTrue();
    }
    finally {
        Carbon::setTestNow();
    }
});

it('is not verified when the "Verified until" date is in the past', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 24, 0, 0, 0, 'UTC'));

    try {
        $entry = ReviewerEntryData::fromArray(reviewerEntryRow([
            'verifiedUntilDate' => '2026-06-15 22:00:00',
        ]));

        expect($entry->isVerified())->toBeFalse();
    }
    finally {
        Carbon::setTestNow();
    }
});

it('is not verified when the "Verified until" date is null (Indefinitely)', function () {
    $entry = ReviewerEntryData::fromArray(reviewerEntryRow([
        'verifiedUntilDate' => null,
    ]));

    expect($entry->isVerified())->toBeFalse();
});


// getCpEditUrl()
// =================================================================================================

it('builds a CP edit URL scoped to the entry site', function () {
    $entry = ReviewerEntryData::fromArray(reviewerEntryRow());

    expect($entry->getCpEditUrl())
        ->toContain('entries/testEntries/128-test-2-en')
        ->toContain('site=sandboxEn');
});


// jsonSerialize()
// =================================================================================================

it('serializes to the display shape the Admin Table expects', function () {
    Carbon::setTestNow(Carbon::create(2026, 6, 24, 0, 0, 0, 'UTC'));

    try {
        $row = reviewerEntryRow();
        $entry = ReviewerEntryData::fromArray($row);

        $json = $entry->jsonSerialize();

        expect($json)->toHaveKeys([
            'id', 'entryId', 'siteId', 'reviewerId', 'verifiedUntilDate', 'sectionId',
            'title', 'slug', 'dateUpdated', 'sectionName', 'sectionHandle', 'siteHandle',
            'siteName', 'isVerified', 'url',
        ]);

        // id-like fields serialize as ints, not numeric strings
        expect($json['id'])->toBe(5)
            ->and($json['entryId'])->toBe(128)
            ->and($json['reviewerId'])->toBe(1);

        // derived / display fields
        expect($json['isVerified'])->toBe('Verified')
            ->and($json['url'])->toBe($entry->getCpEditUrl())
            ->and($json['verifiedUntilDate'])
                ->toBe(DateHelper::readableVerificationDate(DateHelper::toDateTime($row['verifiedUntilDate'])))
            ->and($json['dateUpdated'])
                ->toBe((new Formatter())->asDate(DateHelper::toDateTime($row['dateUpdated'])));

        // straight passthrough
        expect($json['sectionName'])->toBe('Test Entries')
            ->and($json['siteName'])->toBe('Sandbox EN');
    }
    finally {
        Carbon::setTestNow();
    }
});

it('labels a null (Indefinitely) date as Expired - preserves the original transform behaviour', function () {
    // NOTE: a reviewer entry with no "Verified until" date is labelled 'Expired' here, matching
    // the pre-refactor transform. This is arguably wrong (the element-index SQL treats a null date
    // as verified). Encoded as-is to keep the refactor behaviour-preserving - see chat note.
    $row = reviewerEntryRow(['verifiedUntilDate' => null]);

    $json = ReviewerEntryData::fromArray($row)->jsonSerialize();

    expect($json['isVerified'])->toBe('Expired')
        ->and($json['verifiedUntilDate'])->toBe(DateHelper::readableVerificationDate(null));
});
