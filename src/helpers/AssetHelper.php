<?php

namespace webhubworks\verifiedelements\helpers;

use craft\elements\Asset;
use webhubworks\verifiedelements\base\VerifiableElementInterface;

/**
 * Helper methods for Asset elements.
 */
class AssetHelper
{
    /**
     * Determines whether a save changed the asset's content in a way the Reviewer
     * should hear about: a file replacement, an alt-text change, or a change to any
     * custom field. Assets also fire saves for moves, renames, focal-point changes,
     * and indexing - those are noise, not content changes.
     *
     * @param Asset&VerifiableElementInterface $asset
     * @param bool $isNew
     * @return bool
     */
    public static function hasNotifiableContentChange(Asset $asset, bool $isNew): bool
    {
        // A fresh upload (or a duplicate) is a creation, not a change.
        if ($isNew) {
            return false;
        }

        if ($asset->getScenario() === Asset::SCENARIO_REPLACE) {
            return true;
        }

        // Alt is a native attribute, not a custom field, so it never appears in
        // getDirtyFields(); onBeforeSaveAsset() compared it against the stored value.
        if ($asset->getIsAltChanged()) {
            return true;
        }

        // Resaves re-apply every field value, so Craft reports them ALL as dirty -
        // that's bulk maintenance, not a content change.
        if ($asset->resaving) {
            return false;
        }

        return $asset->getDirtyFields() !== [];
    }
}
