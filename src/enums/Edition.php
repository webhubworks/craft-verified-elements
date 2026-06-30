<?php

namespace webhubworks\verifiedentries\enums;

/**
 * Enum representing the different editions of this plugin that a user can purchase to unlock
 * different features.
 *
 * To add a new edition:
 * 1. Create its case below.
 * 2. Add its label to the method below.
 * 3. Assuming the edition will be offered to users now, add it to the "currentlyAvailable" method.
 * 4. Choose which features it allows in the Feature enum.
 * @see Feature
 *
 * You don't need to edit the plugin's primary class or its `editions` method.
 */
enum Edition: string
{
    /**
     * NOTE: These handles can never change. You can add new editions, but existing handles must
     * remain, even if unused in the future.
     */
    case Lite = 'lite';
    case Pro = 'pro';
    case ProPlus = 'pro-plus';

    /**
     * Note that labels for the handles are safe to edit. Only the handles can't be edited.
     *
     * @return string
     */
    public function label(): string
    {
        return match ($this) {
            self::Lite => 'Lite',
            self::Pro => 'Pro',
            self::ProPlus => 'Pro Plus',
        };
    }

    public function handle(): string
    {
        return $this->value;
    }

    /**
     * The editions for this plugin that are currently offered to consumers.
     *
     * @return string[]
     */
    public static function currentlyAvailable(): array
    {
        // list each edition from lowest to highest
        return [
            self::Lite->handle(),
            self::Pro->handle(),
            self::ProPlus->handle(),
        ];
    }
}
