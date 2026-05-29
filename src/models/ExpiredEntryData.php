<?php

namespace webhubworks\verifiedentries\models;

use craft\helpers\UrlHelper;
use webhubworks\verifiedentries\db\PluginQuery;

/**
 * Object representing data returned from querying for all verifiable entries that have
 * verification dates in the past.
 * @see PluginQuery::expiredVerifiableEntries()
 */
readonly class ExpiredEntryData
{
    public function __construct(
        public int     $id, // entry id
        public int     $siteId,
        public ?int     $reviewerId,
        public string  $verifiedUntilDate,
        public int     $sectionId,
        public ?string $title,
        public string  $sectionHandle,
        public string  $siteHandle,
    )
    {
    }

    /**
     * Mass-assign the object from an array.
     *
     * @param array $row
     * @return self
     */
    public static function fromArray(array $row): self
    {
        return new self(
            id: (int)$row['entryId'],
            siteId: (int)$row['siteId'],
            reviewerId: isset($row['reviewerId']) ? (int)$row['reviewerId'] : null,
            verifiedUntilDate: $row['verifiedUntilDate'],
            sectionId: (int)$row['sectionId'],
            title: $row['title'] ?? null,
            sectionHandle: $row['sectionHandle'],
            siteHandle: $row['siteHandle'],
        );
    }

    /**
     * Returns the URL to an entry's "edit" page in the CP, taking into account the entry's site handle.
     *
     * @return string
     */
    public function getCpEditUrl(): string
    {
        return UrlHelper::cpUrl(
            "entries/{$this->sectionHandle}/{$this->id}",
            ['site' => $this->siteHandle]
        );
    }

    /**
     * Returns a string representation of the object, which is the entry title.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->title ?? 'No Title';
    }
}