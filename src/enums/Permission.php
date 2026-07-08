<?php

namespace webhubworks\verifiedelements\enums;

/**
 * Enum representing user permissions for verifying entries.
 */
enum Permission: string
{
    case ManageVerificationSettings = 'verified-elements:manageVerificationSettings';
    case VerifyAssets = 'verified-elements:verifyAssets';
    case VerifyEntries = 'verified-elements:verifyEntries';
}
