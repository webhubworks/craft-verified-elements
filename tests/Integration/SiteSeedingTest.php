<?php

use craft\db\Query;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\Db;
use markhuot\craftpest\factories\Volume;
use webhubworks\verifiedelements\db\PluginTable;
use webhubworks\verifiedelements\enums\Edition;
use webhubworks\verifiedelements\Plugin;

/**
 * INTEGRATION TESTS
 * @see \webhubworks\verifiedelements\services\singletons\PluginSettings::seedContainerSettingsForNewSite()
 *
 * When a site is created, site-agnostic container settings (volumes/assets) must be seeded for it -
 * their config is fanned out to a row per site at save time, so a site created afterwards would
 * otherwise have no rows until an admin re-saved the settings. Per-site types (entries) are managed
 * per site through the UI and must NOT be seeded.
 *
 * The seed only processes element types whose feature is enabled, so these tests force the pro-plus
 * edition (the test DB installs at the default, lite - where asset verification is off) and restore
 * it afterwards, since the plugin instance is shared across the test process.
 */

/**
 * Runs the given assertions with the plugin forced to the pro-plus edition (asset verification on),
 * restoring the original edition afterwards even if an assertion fails.
 *
 * @param Closure $test
 * @return void
 */
function withProPlusEdition(Closure $test): void
{
    $plugin = Plugin::getInstance();
    $originalEdition = $plugin->edition;
    $plugin->edition = Edition::ProPlus->handle();

    try {
        $test();
    } finally {
        $plugin->edition = $originalEdition;
    }
}

it('seeds site-agnostic (asset) container settings for a newly created site', function() {
    withProPlusEdition(function() {
        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;
        $reviewer = getSharedReviewer('a');

        $volume = Volume::factory()->create();

        // The representative row that saveVolumeSettings() would have fanned out to every site.
        Db::insert(PluginTable::CONTAINERS, [
            'containerId' => $volume->id,
            'siteId' => $primarySiteId,
            'elementType' => Asset::class,
            'reviewerId' => $reviewer->id,
            'enabled' => true,
            'defaultPeriod' => 'P30D',
        ]);

        $newSite = createSite('seed');

        Plugin::getInstance()->getPluginSettings()->seedContainerSettingsForNewSite($newSite->id);

        $rows = (new Query())
            ->from(PluginTable::CONTAINERS)
            ->where([
                'containerId' => $volume->id,
                'siteId' => $newSite->id,
                'elementType' => Asset::class,
            ])
            ->all();

        // Exactly one row (upsert is idempotent even though site creation also fires the seed),
        // carrying the representative site's values.
        expect($rows)->toHaveCount(1);
        expect((int)$rows[0]['reviewerId'])->toBe($reviewer->id)
            ->and((bool)$rows[0]['enabled'])->toBeTrue()
            ->and($rows[0]['defaultPeriod'])->toBe('P30D');
    });
});

it('does not seed per-site (entry) container settings for a new site', function() {
    withProPlusEdition(function() {
        $primarySiteId = Craft::$app->getSites()->getPrimarySite()->id;
        $section = createSection();

        Db::insert(PluginTable::CONTAINERS, [
            'containerId' => $section->id,
            'siteId' => $primarySiteId,
            'elementType' => Entry::class,
            'enabled' => true,
        ]);

        $newSite = createSite('seedEntries');

        Plugin::getInstance()->getPluginSettings()->seedContainerSettingsForNewSite($newSite->id);

        // Entries are per-site: the seed runs (assets would be processed) but must skip them.
        $entryRowExists = (new Query())
            ->from(PluginTable::CONTAINERS)
            ->where([
                'containerId' => $section->id,
                'siteId' => $newSite->id,
                'elementType' => Entry::class,
            ])
            ->exists();

        expect($entryRowExists)->toBeFalse();
    });
});
