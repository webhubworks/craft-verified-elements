<?php

namespace webhubworks\verifiedelements\enums;

use webhubworks\verifiedelements\Plugin;

/**
 * Enum representing the single source of truth for which edition unlocks which feature.
 *
 * To add a new feature:
 * 1. Create its case below.
 * 2. Edit the `match` arm in `requiredEdition` method below, selecting which edition it belongs to.
 * 3. Update the codebase where applicable to conditionally check if the feature is enabled in the
 *    edition that the user has purchased.
 *
 * @see Edition
 */
enum Feature
{
    // LIST of FEATURES
    // =============================================================================================

    /**
     * Will the plugin allow verifying Asset elements?
     */
    case AssetVerification;

    /**
     * Will the plugin allow verifying Entry elements?
     */
    case EntryVerification;

    /**
     * Will the plugin allow enabling sections from multiple sites or just the primary site?
     */
    case MultiSite;


    // PER EDITION
    // =============================================================================================

    /**
     * For a given feature in this enum, output the base edition required to unlock the feature.
     * Editions are stacked, so the more expensive editions inherit all the features of cheaper
     * editions.
     */
    public function requiredEdition(): Edition
    {
        return match ($this) {
            self::EntryVerification => Edition::Lite,
            self::MultiSite, self::AssetVerification => Edition::Pro,
        };
    }

    /**
     * Whether the plugin's current edition unlocks this feature.
     *
     * Note: the `>=` operator is required - Craft's `Plugin::is()` defaults to an EXACT edition
     * match, which would lock lower-tier features out of higher editions.
     */
    public function isEnabled(): bool
    {
        return Plugin::getInstance()->is($this->requiredEdition()->handle(), '>=');
    }
}
