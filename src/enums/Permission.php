<?php

namespace webhubworks\verifiedelements\enums;

use Craft;

/**
 * Enum representing user permissions for using this plugin.
 */
enum Permission: string
{
    /**
     * Craft's own "Access Verified Elements" permission.
     *
     * Craft creates and enforces this for every plugin with a CP section (it's NOT registered by
     * onRegisterUserPermissions). It appears here only so plugin code can reference it. Craft
     * stores it lowercase; permission checks are case-insensitive.
     */
    case AccessPlugin = 'accessPlugin-verified-elements';

    /**
     * Grants managing the plugin's settings.
     *
     * Includes:
     * 1. Viewing the plugin's Settings subnav item.
     * 2. Registering the settings URL rules.
     */
    case ManagePluginSettings = 'verified-elements:managePluginSettings';

    /**
     * Grants verifying assets.
     *
     * Includes:
     * 1. Editing the verification fields (edit-page sidebar and index inline inputs).
     * 2. Using the "Verify"/"Assign Reviewer" bulk actions on asset indexes. Holders are
     *    also the users offered as assignable reviewers for assets.
     */
    case VerifyAssets = 'verified-elements:verifyAssets';

    /**
     * Grants verifying entries.
     *
     * Includes:
     * 1. Editing the verification fields (edit-page sidebar and index inline inputs).
     * 2. Using the "Verify"/"Assign Reviewer" bulk actions on entry indexes. Holders are
     *    also the users offered as assignable reviewers for entries.
     */
    case VerifyEntries = 'verified-elements:verifyEntries';

    /**
     * Returns whether the current user holds this permission.
     *
     * Admins pass implicitly. User::can() short-circuits for them, so callers never need a
     * separate getIsAdmin() check. Safe in both web and console contexts (both user components
     * implement checkPermission()).
     */
    public function isGranted(): bool
    {
        return Craft::$app->getUser()->checkPermission($this->value);
    }
}
