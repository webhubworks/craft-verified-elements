<?php

namespace webhubworks\verifiedelements\models;

use Craft;
use craft\elements\User;
use webhubworks\verifiedelements\base\NotifiableInterface;

/**
 * This class represents an email recipient for the plugin's notifications when a User element is
 * assigned to keep an entry verified.
 */
class UserRecipient implements NotifiableInterface
{
    public function __construct(
        protected User $user
    )
    {
    }

    /** @inheritDoc */
    public function getEmail(): string
    {
        return $this->user->email;
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return $this->user->name;
    }

    /** @inheritDoc */
    public function getFriendlyName(): string
    {
        return $this->user->getFriendlyName();
    }

    /** @inheritDoc */
    public function getLocale(): ?string
    {
        $locale = $this->user->getPreferredLocale();

        if (! $locale) {
            $locale = Craft::$app->language;
        }

        return $locale;
    }

    /** @inheritDoc */
    public function getId(): ?int
    {
        return $this->user->id;
    }
}
