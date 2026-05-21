<?php

namespace webhubworks\verifiedentries\enums;

/**
 * Enum representing user permissions for verifying entries.
 */
enum Permission: string
{
    case VerifyEntries = 'verified-entries:verifyEntries';
    case ManageVerificationSettings = 'verified-entries:manageVerificationSettings';
}
