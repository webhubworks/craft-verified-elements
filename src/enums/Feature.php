<?php

namespace webhubworks\verifiedentries\enums;

use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Enum representing the single source of truth for which edition unlocks which feature.
 *
 * To change what an edition includes, edit the match arm in `requiredEdition` and nothing else in
 * the codebase needs to touch an edition handle directly.
 */
enum Feature
{
    // LIST of FEATURES
    // =============================================================================================

    /**
     * Will the plugin allow enabling sections from multiple sites or just the primary site?
     */
    case MultiSite;

    /**
     * Will the plugin allow verifying Asset elements?
     */
    case AssetVerification;

    /**
     * Will the plugin allow verifying Entry elements?
     */
    case EntryVerification;

    /**
     * Will the plugin allow assigning a Reviewer (Craft user) to keep an element updated or must
     * a single user be the sole Reviewer?
     */
    case ReviewerAssignment;


    // PER EDITION
    // =============================================================================================

    /**
     * The plugin's lowest required edition that unlocks this feature.
     */
    public function requiredEdition(): Edition
    {
        return match ($this) {
            self::EntryVerification, self::ReviewerAssignment => Edition::Lite,
            self::AssetVerification => Edition::Pro,
            self::MultiSite => Edition::Basic,
        };
    }

    /**
     * Whether the plugin's current edition unlocks this feature.
     */
    public function isEnabled(): bool
    {
        return VerifiedEntries::getInstance()->is($this->requiredEdition()->handle());
    }
}
