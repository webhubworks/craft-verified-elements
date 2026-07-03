<?php

namespace webhubworks\verifiedelements\enums;

use craft\base\Element;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use craft\models\Section;
use craft\models\Volume;
use webhubworks\verifiedelements\elements\VerifiedAsset;
use webhubworks\verifiedelements\elements\VerifiedEntry;

/**
 * Registry of the element types this plugin can verify.
 *
 * Each case's value is the vanilla element FQCN - the same string stored in the
 * `verifiedelements_containers.elementType` column and carried by `ElementData::$type` - so
 * `ElementType::from($row['elementType'])` converts a DB discriminator straight into the registry.
 *
 * All per-type knowledge (which container an element belongs to, the plugin's element subtype,
 * display labels) lives here. Adding a new element type means adding a case and filling in the
 * `match` arms below; static analysis flags any arm that's missed.
 */
enum ElementType: string
{
    case Entry = Entry::class;
    case Asset = Asset::class;

    /**
     * Resolves the registry case for a live element.
     *
     * Note: this checks `instanceof` rather than `self::from($element::class)` so the plugin's
     * own element subtypes (VerifiedEntry, VerifiedAsset) resolve to their vanilla type - the
     * subtype FQCNs would never match the case values.
     *
     * @param Element $element
     * @return self
     */
    public static function fromElement(Element $element): self
    {
        return match (true) {
            $element instanceof Entry => self::Entry,
            $element instanceof Asset => self::Asset,
        };
    }

    /**
     * Like fromElement(), but returns null for unsupported element types instead of throwing.
     *
     * @param Element $element
     * @return self|null
     */
    public static function tryFromElement(Element $element): ?self
    {
        return match (true) {
            $element instanceof Entry => self::Entry,
            $element instanceof Asset => self::Asset,
            default => null,
        };
    }

    /**
     * Returns the ID of the container (section, volume, ...) the element belongs to.
     *
     * @param Element $element
     * @return int
     */
    public function containerId(Element $element): int
    {
        /** @var Entry|Asset $element */
        return match ($this) {
            self::Entry => $element->sectionId,
            self::Asset => $element->volumeId,
        };
    }

    /**
     * Returns the container model (section, volume, ...) the element belongs to.
     *
     * @param Element $element
     * @return Section|Volume
     */
    public function container(Element $element): Section|Volume
    {
        /** @var Entry|Asset $element */
        return match ($this) {
            self::Entry => $element->getSection(),
            self::Asset => $element->getVolume(),
        };
    }

    /**
     * Returns the plugin's element subtype that powers the dashboard element index for this type.
     *
     * @return string
     */
    public function verifiedElementClass(): string
    {
        return match ($this) {
            self::Entry => VerifiedEntry::class,
            self::Asset => VerifiedAsset::class,
        };
    }

    /**
     * Returns a human-readable label for log messages and UI text.
     *
     * @param bool $plural
     * @param bool $capitalize
     * @return string
     */
    public function label(bool $plural = false, bool $capitalize = true): string
    {
        $label = match ($this) {
            self::Entry => $plural ? 'entries' : 'entry',
            self::Asset => $plural ? 'assets' : 'asset',
        };

        if ($capitalize) {
            return StringHelper::upperCaseFirst($label);
        }

        return $label;
    }
}
