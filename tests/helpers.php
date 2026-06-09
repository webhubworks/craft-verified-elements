<?php

use craft\elements\Entry as EntryElement;
use craft\elements\db\EntryQuery;
use craft\errors\SiteNotFoundException;
use craft\models\Site;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\behaviors\VerifiableQueryBehavior;

/**
 * Helper for ensuring entries have the behavior class that the plugin normally directs Craft to
 * attach to all entries.
 *
 * @param EntryElement $entry
 * @return EntryElement|VerifiableBehavior
 */
function withVerifiableBehavior(EntryElement $entry): EntryElement|VerifiableBehavior
{
    $entry->attachBehavior(VerifiableBehavior::NAME, VerifiableBehavior::class);

    return $entry;
}

/**
 * Helper for ensuring entry queries have the behavior class that the plugin normally directs
 * Craft to attach to all entries.
 *
 * @param EntryQuery $query
 * @return EntryQuery|VerifiableQueryBehavior
 */
function withVerifiableQueryBehavior(EntryQuery $query): EntryQuery|VerifiableQueryBehavior
{
    $query->attachBehavior(VerifiableQueryBehavior::NAME, VerifiableQueryBehavior::class);

    return $query;
}

/**
 * Generates a `Site` model for testing.
 *
 * @param string $name
 * @param string $handle
 * @return Site
 * @throws Throwable
 * @throws SiteNotFoundException
 */
function createSite(string $name, string $handle): Site
{
    /** @noinspection NonSecureUniqidUsageInspection */
    $suffix = uniqid();
    $name .= ' ' . $suffix;
    $handle .= '_' . $suffix;
    $primarySite = Craft::$app->getSites()->getPrimarySite();
    $site = new Site([
        'groupId' => $primarySite->groupId,
        'name' => $name,
        'handle' => $handle,
        'language' => 'en-US',
        'baseUrl' => '@web/' . $handle,
    ]);

    $isSaved = Craft::$app->getSites()->saveSite($site);
    if (! $isSaved) {
        throw new RuntimeException('Failed to save site: ' . implode(', ', $site->getFirstErrors()));
    }

    return $site;
}
