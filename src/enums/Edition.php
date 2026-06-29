<?php

namespace webhubworks\verifiedentries\enums;

/**
 * Enum representing the different editions of this plugin that a user can purchase to unlock
 * different features.
 */
enum Edition: string
{
    /**
     * NOTE: These handles can never change. You can add new editions, but existing handles must
     * remain, even if unused in the future.
     */
    case Lite = 'lite';
    case Basic = 'basic';
    case Pro = 'pro';

    public function label(): string
    {
        return match ($this) {
            self::Lite => 'Lite',
            self::Basic => 'Basic',
            self::Pro => 'Pro',
        };
    }

    public function handle(): string
    {
        return $this->value;
    }
}
