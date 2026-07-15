<?php

use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use webhubworks\verifiedelements\elements\VerifiedAsset;
use webhubworks\verifiedelements\elements\VerifiedEntry;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\Feature;
use webhubworks\verifiedelements\enums\Permission;
use webhubworks\verifiedelements\helpers\Log;
use yii\base\InvalidArgumentException;

/**
 * UNIT TESTS
 * @see ElementType Enum
 *
 * Tests the element-type registry: every piece of per-type knowledge (container resolution,
 * verified element subtype, labels) must resolve correctly from live elements, from the plugin's
 * own element subtypes, and from the FQCN strings stored in the database.
 */


// Case resolution
// =================================================================================================

it('resolves vanilla elements to their registry case', function() {
    expect(ElementType::fromElement(new Entry()))->toBe(ElementType::Entry);
    expect(ElementType::fromElement(new Asset()))->toBe(ElementType::Asset);
});

it("resolves the plugin's element subtypes to their vanilla case", function() {
    expect(ElementType::fromElement(new VerifiedEntry()))->toBe(ElementType::Entry);
    expect(ElementType::fromElement(new VerifiedAsset()))->toBe(ElementType::Asset);
});

it('resolves the DB discriminator string to its registry case', function() {
    expect(ElementType::from(Entry::class))->toBe(ElementType::Entry);
    expect(ElementType::from(Asset::class))->toBe(ElementType::Asset);
});

it('returns null when trying to resolve an unsupported element type', function() {
    expect(ElementType::tryFromElement(new User()))->toBeNull();
});

it('resolves element class names to their registry case', function() {
    expect(ElementType::fromElementClass(Entry::class))->toBe(ElementType::Entry);
    expect(ElementType::fromElementClass(Asset::class))->toBe(ElementType::Asset);
});

it("resolves the plugin's element subtype class names to their vanilla case", function() {
    expect(ElementType::fromElementClass(VerifiedEntry::class))->toBe(ElementType::Entry);
    expect(ElementType::fromElementClass(VerifiedAsset::class))->toBe(ElementType::Asset);
});

it('throws for unsupported elements', function() {
    ElementType::fromElement(new User());
})->throws(InvalidArgumentException::class);

it('throws for unsupported element class names', function() {
    ElementType::fromElementClass(User::class);
})->throws(InvalidArgumentException::class);

it('builds the CP edit URI per type', function() {
    expect(ElementType::Entry->cpEditUri(128, 'testEntries', 'test-2-en'))
        ->toBe('entries/testEntries/128-test-2-en');
    expect(ElementType::Asset->cpEditUri(128, 'testVolume', null))
        ->toBe('assets/edit/128');
});


// Per-type knowledge
// =================================================================================================

it("reads the container ID off the element's type-specific attribute", function() {
    $entry = new Entry(['sectionId' => 3]);
    $asset = new Asset(['volumeId' => 7]);

    expect(ElementType::Entry->containerId($entry))->toBe(3);
    expect(ElementType::Asset->containerId($asset))->toBe(7);
});

it('maps each type to its verified element subtype', function() {
    expect(ElementType::Entry->verifiedElementClass())->toBe(VerifiedEntry::class);
    expect(ElementType::Asset->verifiedElementClass())->toBe(VerifiedAsset::class);
});

it('maps each type to its feature gate', function() {
    expect(ElementType::Entry->feature())->toBe(Feature::EntryVerification);
    expect(ElementType::Asset->feature())->toBe(Feature::AssetVerification);
});

it('maps each type to its verify permission', function() {
    expect(ElementType::Entry->verifyPermission())->toBe(Permission::VerifyEntries);
    expect(ElementType::Asset->verifyPermission())->toBe(Permission::VerifyAssets);
});

it('maps each type to its plugin index URI segment', function() {
    expect(ElementType::Entry->uriSegment())->toBe('entries');
    expect(ElementType::Asset->uriSegment())->toBe('assets');
});

it('maps each type to its DB tables and container column', function() {
    expect(ElementType::Entry->elementTable())->toBe('{{%entries}}');
    expect(ElementType::Entry->containerTable())->toBe('{{%sections}}');
    expect(ElementType::Entry->containerIdColumn())->toBe('sectionId');
    expect(ElementType::Asset->elementTable())->toBe('{{%assets}}');
    expect(ElementType::Asset->containerTable())->toBe('{{%volumes}}');
    expect(ElementType::Asset->containerIdColumn())->toBe('volumeId');
});

it('flags which types have site-agnostic settings', function() {
    // Entries are configured per site; assets (site-less volumes) fan one config out to every site.
    expect(ElementType::Entry->hasSiteAgnosticSettings())->toBeFalse();
    expect(ElementType::Asset->hasSiteAgnosticSettings())->toBeTrue();
});

it('builds singular, plural, and capitalized labels', function() {
    expect(ElementType::Entry->label())->toBe('Entry');
    expect(ElementType::Entry->label(plural: true, capitalize: false))->toBe('entries');
    expect(ElementType::Asset->label(capitalize: false))->toBe('asset');
    expect(ElementType::Asset->label(plural: true))->toBe('Assets');
});


// Log::element() delegation
// =================================================================================================

it('labels supported types through Log::element', function() {
    expect(Log::element(Entry::class))->toBe('Entry');
    expect(Log::element(new Asset(), plural: true, capitalize: false))->toBe('assets');
});

it('keeps unknown element types identifiable in Log::element', function() {
    expect(Log::element('some\unknown\ElementClass'))->toBe('some\unknown\ElementClass');
    expect(Log::element(new User()))->toBe('');
    expect(Log::element(123))->toBe('');
});
