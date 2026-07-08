<?php

namespace webhubworks\verifiedelements\enums;

use craft\base\Element;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\Entry;
use craft\helpers\StringHelper;
use craft\models\Section;
use craft\models\Volume;
use ValueError;
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
     * Resolves the registry case for an element class name.
     *
     * Like fromElement(), this resolves the plugin's own element subtypes (VerifiedEntry,
     * VerifiedAsset) to their vanilla case. `is_a()` walks the inheritance chain, where
     * `self::from()` would only match the exact case values.
     *
     * @param class-string<Element> $elementClass
     * @return self
     */
    public static function fromElementClass(string $elementClass): self
    {
        return match (true) {
            is_a($elementClass, Entry::class, true) => self::Entry,
            is_a($elementClass, Asset::class, true) => self::Asset,
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
     * Returns the feature gate that controls this element type.
     *
     * @return Feature
     */
    public function feature(): Feature
    {
        return match ($this) {
            self::Entry => Feature::EntryVerification,
            self::Asset => Feature::AssetVerification,
        };
    }

    /**
     * Returns the permission required to verify elements of this type.
     *
     * @return Permission
     */
    public function verifyPermission(): Permission
    {
        return match ($this) {
            self::Entry => Permission::VerifyEntries,
            self::Asset => Permission::VerifyAssets,
        };
    }

    /**
     * Returns the FQCNs of the element types whose features are enabled in the plugin's current
     * edition.
     *
     * @return string[]
     */
    public static function enabledTypes(): array
    {
        $enabledCases = array_filter(
            self::cases(),
            static fn(self $elementType) => $elementType->feature()->isEnabled()
        );

        return array_map(
            static fn(self $elementType) => $elementType->value,
            array_values($enabledCases)
        );
    }

    /**
     * Returns the DB table holding this element type's own rows.
     *
     * @return string
     */
    public function elementTable(): string
    {
        return match ($this) {
            self::Entry => Table::ENTRIES,
            self::Asset => Table::ASSETS,
        };
    }

    /**
     * Returns the DB table holding this element type's containers (sections, volumes, ...).
     *
     * @return string
     */
    public function containerTable(): string
    {
        return match ($this) {
            self::Entry => Table::SECTIONS,
            self::Asset => Table::VOLUMES,
        };
    }

    /**
     * Returns the column on this element type's own table that stores the container ID.
     *
     * @return string
     * @see elementTable()
     */
    public function containerIdColumn(): string
    {
        return match ($this) {
            self::Entry => 'sectionId',
            self::Asset => 'volumeId',
        };
    }

    /**
     * Returns the URI segment used for this type's index pages in the plugin's CP section,
     * e.g. the 'entries' in `verified-elements/entries`.
     *
     * @return string
     */
    public function uriSegment(): string
    {
        return match ($this) {
            self::Entry => 'entries',
            self::Asset => 'assets',
        };
    }

    /**
     * Resolves a URI segment (e.g. an `elementType` query param) back to its registry case.
     *
     * @param string $uriSegment
     * @return self
     * @see uriSegment()
     */
    public static function fromUriSegment(string $uriSegment): self
    {
        foreach (self::cases() as $case) {
            if ($case->uriSegment() === $uriSegment) {
                return $case;
            }
        }

        throw new ValueError(sprintf('Unknown element type URI segment: %s', $uriSegment));
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
