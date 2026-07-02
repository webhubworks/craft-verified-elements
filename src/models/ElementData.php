<?php

namespace webhubworks\verifiedelements\models;

use Craft;
use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\Db;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use JsonSerializable;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\helpers\DateHelper;

/**
 * Element-agnostic value object carrying the generic data every supported element type shares:
 * identity, its container (section for entries, volume for assets, ...), site, and verification
 * state.
 */
readonly class ElementData implements JsonSerializable
{
    public function __construct(
        public string  $type,            // Entry::class, Asset::class, ...
        public ?int    $rowId,           // veea.id - null when built from a live element (no verification row yet)
        public int     $id,              // the element's id (canonical id in a save context)
        public string  $title,
        public int     $siteId,
        public string  $siteName,
        public string  $siteHandle,
        public int     $containerId,     // sectionId (entry) / volumeId (asset)
        public string  $containerName,
        public string  $containerHandle,
        public ?string $dateUpdated,     // raw DB value (UTC)
        public ?int    $reviewerId,
        public ?string $verifiedUntilDate, // raw DB value (UTC); null means "Indefinitely"
        public string  $cpEditUrl,
    ) {}

    /**
     * Hydrate from a raw reviewer/expired query row.
     *
     * @param array $row
     * @return self
     */
    public static function fromArray(array $row): self
    {
        return new self(
            type: $row['type'],
            rowId: isset($row['rowId']) ? (int)$row['rowId'] : null,
            id: (int)$row['id'],
            title: (string)($row['title'] ?? ''),
            siteId: (int)$row['siteId'],
            siteName: $row['siteName'],
            siteHandle: $row['siteHandle'],
            containerId: (int)$row['containerId'],
            containerName: $row['containerName'],
            containerHandle: $row['containerHandle'],
            dateUpdated: $row['dateUpdated'] ?? null,
            reviewerId: isset($row['reviewerId']) ? (int)$row['reviewerId'] : null,
            verifiedUntilDate: $row['verifiedUntilDate'] ?? null,
            cpEditUrl: self::buildCpUrl($row),
        );
    }

    /**
     * Snapshot a live element (with VerifiableBehavior attached).
     *
     * @param Element|VerifiableBehavior $element
     * @return self
     */
    public static function fromElement(Element $element): self
    {
        /** @var VerifiableBehavior $element */

        [$container, $type] = match (true) {
            $element instanceof Entry => [$element->getSection(), Entry::class],
            $element instanceof Asset => [$element->getVolume(), Asset::class],
        };

        $site = $element->getSite();

        return new self(
            type: $type,
            rowId: null,
            id: $element->getCanonicalId(),
            title: (string)$element->title,
            siteId: $element->siteId,
            siteName: $site->getName(),
            siteHandle: $site->handle,
            containerId: $container->id,
            containerName: $container->name,
            containerHandle: $container->handle,
            dateUpdated: Db::prepareDateForDb($element->dateUpdated),
            reviewerId: $element->getReviewerId(),
            verifiedUntilDate: Db::prepareDateForDb($element->getVerifiedUntilDate()),
            cpEditUrl: (string)$element->getCpEditUrl(),
        );
    }

    /**
     * Whether verification is still in the future.
     *
     * @return bool
     */
    public function isVerified(): bool
    {
        $date = DateHelper::toDateTime($this->verifiedUntilDate);

        return VerificationStatus::fromDate($date)->isVerified();
    }

    /**
     * Returns the Craft `User` who's assigned to keep the element verified.
     *
     * @return User|null
     */
    public function getReviewer(): ?User
    {
        if ($this->reviewerId === null) {
            return null;
        }

        return Craft::$app->getUsers()->getUserById($this->reviewerId);
    }

    /**
     * The display shape consumed by the CP "Admin Table" Vue component.
     *
     * @return array
     */
    public function jsonSerialize(): array
    {
        return [
            'type' => $this->type,
            'rowId' => $this->rowId,
            'id' => $this->id,
            'title' => $this->title,
            'siteId' => $this->siteId,
            'siteName' => $this->siteName,
            'siteHandle' => $this->siteHandle,
            'containerId' => $this->containerId,
            'containerName' => $this->containerName,
            'containerHandle' => $this->containerHandle,
            'dateUpdated' => (new Formatter())->asDate(DateHelper::toDateTime($this->dateUpdated)),
            'reviewerId' => $this->reviewerId,
            'verifiedUntilDate' => DateHelper::readableVerificationDate(DateHelper::toDateTime($this->verifiedUntilDate)),
            'isVerified' => $this->isVerified() ? 'Verified' : 'Expired',
            'url' => $this->cpEditUrl,
        ];
    }

    /**
     * Returns a string representation of the object.
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->title ?? 'No Title';
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Build the CP edit URL from a raw query row (no live element to call getCpEditUrl() on).
     * This is the ONE place per-type URL construction lives for the query path; new element types
     * add an arm here. `slug` is used transiently for entries and never becomes a VO field.
     *
     * @param array $row
     * @return string
     */
    private static function buildCpUrl(array $row): string
    {
        $uri = match ($row['type']) {
            Entry::class => sprintf('entries/%s/%s-%s', $row['containerHandle'], $row['id'], $row['slug']),
            Asset::class => sprintf('assets/edit/%s', $row['id']),
            default => '',
        };

        if ($uri === '') {
            return '';
        }

        return UrlHelper::cpUrl($uri, ['site' => $row['siteHandle']]);
    }
}
