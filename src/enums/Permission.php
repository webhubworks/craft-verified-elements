<?php

namespace webhubworks\verifiedelements\enums;

/**
 * Enum representing user permissions for verifying entries.
 */
enum Permission: string
{
    case VerifyEntries = 'verified-elements:verifyEntries';
    case ManageVerificationSettings = 'verified-elements:manageVerificationSettings';
}
