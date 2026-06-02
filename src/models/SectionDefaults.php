<?php

namespace webhubworks\verifiedentries\models;

use webhubworks\verifiedentries\services\singletons\SectionSettings;

/**
 * Default settings for a section (per site) that was saved in the plugin's settings page in the CP.
 */
readonly class SectionDefaults
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
     * A unique identifier for memorizing this object.
     *
     * @param int $sectionId
     * @param int $siteId
     * @return string
     * @see SectionSettings::getDefaultSettingsForSection()
     */
    public static function key(int $sectionId, int $siteId): string
    {
        return implode('_', [
            'section',
            $sectionId,
            'site',
            $siteId
        ]);
    }
}
