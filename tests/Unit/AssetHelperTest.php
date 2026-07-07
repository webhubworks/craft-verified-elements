<?php

use craft\elements\Asset;
use webhubworks\verifiedelements\helpers\AssetHelper;

/**
 * UNIT TESTS
 * @see AssetHelper
 *
 * Covers the save-noise classification behind reviewer change notifications (D4): a file
 * replacement, an alt-text change, or a custom field edit notifies; creations, resaves, and
 * metadata-only saves (move/rename/focal point/index) stay silent.
 */

// hasNotifiableContentChange()
// =================================================================================================

it('never treats a new asset as a content change, even when a save looks change-like', function () {
    $asset = withVerifiableBehavior(new Asset());
    $asset->setScenario(Asset::SCENARIO_REPLACE);
    $asset->altChanged = true;

    expect(AssetHelper::hasNotifiableContentChange($asset, isNew: true))->toBeFalse();
});

it('treats a file replacement as a content change', function () {
    $asset = withVerifiableBehavior(new Asset());
    $asset->setScenario(Asset::SCENARIO_REPLACE);

    expect(AssetHelper::hasNotifiableContentChange($asset, isNew: false))->toBeTrue();
});

it('treats an alt-text change as a content change', function () {
    $asset = withVerifiableBehavior(new Asset());
    $asset->altChanged = true;

    expect(AssetHelper::hasNotifiableContentChange($asset, isNew: false))->toBeTrue();
});

it('treats a custom field edit as a content change', function () {
    $asset = withVerifiableBehavior(new Asset());
    $asset->setDirtyFields(['plainTextField']);

    expect(AssetHelper::hasNotifiableContentChange($asset, isNew: false))->toBeTrue();
});

it('never treats a resave as a content change, even with dirty fields', function () {
    $asset = withVerifiableBehavior(new Asset());
    $asset->resaving = true;
    $asset->setDirtyFields(['plainTextField']);

    expect(AssetHelper::hasNotifiableContentChange($asset, isNew: false))->toBeFalse();
});

it('treats a save without a replacement, alt change, or field edit as noise', function () {
    $asset = withVerifiableBehavior(new Asset());

    expect(AssetHelper::hasNotifiableContentChange($asset, isNew: false))->toBeFalse();
});
