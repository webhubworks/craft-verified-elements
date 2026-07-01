<?php

namespace webhubworks\verifiedelements\models;

use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use DateTime;
use JsonSerializable;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\helpers\DateHelper;

/**
 * Object representing a single element assigned to a Reviewer, as returned by the paginated
 * reviewer dashboard query. Hydrated from a raw query row and serialized to the shape the CP
 * "Admin Table" Vue component expects.
 *
 * @see PluginQuery::entriesByReviewer()
 * @see \webhubworks\verifiedelements\services\singletons\Reviewers::getPaginatedEntries()
 */
readonly class ReviewerEntryData implements JsonSerializable
{
    public function __construct(
        public string  $elementType,
        public int     $id, // verification attribute row id (veea.id), NOT the element's id
        public int     $elementId,
        public int     $siteId,
        public ?int    $reviewerId,
        public ?string $verifiedUntilDate, // raw DB value; null means "Indefinitely"
        public int     $containerId,
        public string  $title,
        public string  $slug,
        public string  $dateUpdated, // raw DB value
        public string  $sectionName,
        public string  $sectionHandle,
        public string  $siteHandle,
        public string  $siteName,
    ) {}

    /**
     * Mass-assign the object from a raw query row.
     *
     * @param array $row
     * @return self
     */
    public static function fromArray(array $row): self
    {
        return new self(
            elementType: $row['elementType'],
            id: (int)$row['id'],
            elementId: (int)$row['elementId'],
            siteId: (int)$row['siteId'],
            reviewerId: isset($row['reviewerId']) ? (int)$row['reviewerId'] : null,
            verifiedUntilDate: $row['verifiedUntilDate'] ?? null,
            containerId: (int)$row['containerId'],
            title: $row['title'],
            slug: $row['slug'],
            dateUpdated: $row['dateUpdated'],
            sectionName: $row['sectionName'],
            sectionHandle: $row['sectionHandle'],
            siteHandle: $row['siteHandle'],
            siteName: $row['siteName'],
        );
    }

    /**
     * Whether the element's verification is still in the future. A null "Verified until" date
     * ("Indefinitely") counts as NOT verified here, matching the original transform behaviour.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        $verifiedUntilDate = DateHelper::toDateTime($this->verifiedUntilDate);

        return $verifiedUntilDate instanceof DateTime && $verifiedUntilDate > DateHelper::now();
    }

    /**
     * Returns the URL to the element's "edit" page in the CP, scoped to the element's site.
     *
     * @return string
     */
    public function getCpEditUrl(): string
    {
        $uri = sprintf('entries/%s/%s-%s', $this->sectionHandle, $this->elementId, $this->slug);

        return UrlHelper::cpUrl($uri, ['site' => $this->siteHandle]);
    }

    /**
     * Returns the display shape consumed by the CP "Admin Table" Vue component. Mirrors the keys
     * the previous array-based transform produced, so the frontend contract is unchanged.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        $verifiedUntilDate = DateHelper::toDateTime($this->verifiedUntilDate);

        return [
            'elementType' => $this->elementType,
            'id' => $this->id,
            'elementId' => $this->elementId,
            'siteId' => $this->siteId,
            'reviewerId' => $this->reviewerId,
            'verifiedUntilDate' => DateHelper::readableVerificationDate($verifiedUntilDate),
            'containerId' => $this->containerId,
            'title' => $this->title,
            'slug' => $this->slug,
            'dateUpdated' => (new Formatter())->asDate(DateHelper::toDateTime($this->dateUpdated)),
            'sectionName' => $this->sectionName,
            'sectionHandle' => $this->sectionHandle,
            'siteHandle' => $this->siteHandle,
            'siteName' => $this->siteName,
            'isVerified' => $this->isVerified() ? 'Verified' : 'Expired',
            'url' => $this->getCpEditUrl(),
        ];
    }
}
