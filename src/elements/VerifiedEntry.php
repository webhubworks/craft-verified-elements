<?php

/** @noinspection PhpUnhandledExceptionInspection */

namespace webhubworks\verifiedelements\elements;

use Craft;
use craft\elements\Entry;
use webhubworks\verifiedelements\Plugin;
use webhubworks\verifiedelements\services\ElementIndexSourcesBuilder;

/**
 * Entry subtype that powers the plugin's dashboard element index, defining its sidebar sources
 * and default table columns.
 */
class VerifiedEntry extends Entry
{
    /** @inheritDoc */
    public static function refHandle(): ?string
    {
        return 'verifiedEntry';
    }

    /** @inheritDoc */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'section',
            'isVerified',
            'verifiedUntilDate',
            'reviewer',
            'postDate',
        ];
    }

    /** @inheritDoc */
    protected static function defineSources(string $context): array
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        $service = new ElementIndexSourcesBuilder(
            elementType: Entry::class,
            currentUserId: $currentUser->id,
            currentUserName: $currentUser->getFriendlyName(),
            unassignedCountBaseQuery: Entry::find()->status(Entry::STATUS_LIVE),
            siteHandle: Craft::$app->getRequest()->getQueryParam('site'),
            settings: Plugin::getInstance()->getPluginSettings(),
        );

        return $service->defineSources();
    }
}
