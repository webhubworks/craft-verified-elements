<?php

namespace webhubworks\verifiedelements\elements;

use Craft;
use craft\elements\Asset;
use webhubworks\verifiedelements\Plugin;
use webhubworks\verifiedelements\services\ElementIndexSourcesBuilder;

/**
 * Asset subtype that powers the plugin's dashboard element index, defining its sidebar sources
 * and default table columns.
 */
class VerifiedAsset extends Asset
{
    /** @inheritDoc */
    public static function refHandle(): ?string
    {
        return 'verifiedAsset';
    }

    /** @inheritDoc */
    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'location',
            'isVerified',
            'verifiedUntilDate',
            'reviewer',
            'dateModified',
            'dateCreated',
        ];
    }

    /** @inheritDoc */
    protected static function defineSources(string $context): array
    {
        $currentUser = Craft::$app->getUser()->getIdentity();

        $service = new ElementIndexSourcesBuilder(
            elementType: Asset::class,
            currentUserId: $currentUser->id,
            currentUserName: $currentUser->getFriendlyName(),
            unassignedCountBaseQuery: Asset::find(),
            siteHandle: Craft::$app->getRequest()->getQueryParam('site'),
            settings: Plugin::getInstance()->getPluginSettings(),
        );

        return $service->defineSources();
    }
}
