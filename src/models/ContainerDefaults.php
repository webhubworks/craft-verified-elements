<?php

namespace webhubworks\verifiedelements\models;

use webhubworks\verifiedelements\services\singletons\PluginSettings;

/**
 * Default settings for a container (section, volume, ...) per site, as saved on the plugin's
 * settings page in the CP.
 */
readonly class ContainerDefaults
{
    public function __construct(
        public int     $id,
        public string  $name,
        public string  $handle,
        public int     $siteId,
        public ?int    $reviewerId,
        public ?string $period,
    ) {}

    /**
     * A unique identifier for memoizing this object.
     *
     * @param int $containerId
     * @param int $siteId
     * @param string $elementType
     * @return string
     * @see PluginSettings::getDefaultSettingsForContainer()
     */
    public static function key(int $containerId, int $siteId, string $elementType): string
    {
        return implode('_', [
            $elementType,
            'container',
            $containerId,
            'site',
            $siteId,
        ]);
    }
}
