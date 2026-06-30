<?php

namespace webhubworks\verifiedelements\models;

use Craft;
use craft\helpers\App;
use webhubworks\verifiedelements\base\NotifiableInterface;

/**
 * This class represents an email recipient for the plugin's notifications in the scenario where
 * there's no Reviewer assigned to an expired or changed entry. It will use the system's defaults.
 */
class SystemRecipient implements NotifiableInterface
{

    /** @inheritDoc */
    public function getEmail(): string
    {
        return App::parseEnv(App::mailSettings()->fromEmail);
    }

    /** @inheritDoc */
    public function getName(): string
    {
        return App::parseEnv(App::mailSettings()->fromName);
    }

    /** @inheritDoc */
    public function getFriendlyName(): string
    {
        return App::parseEnv(App::mailSettings()->fromName);
    }

    /** @inheritDoc */
    public function getLocale(): ?string
    {
        return Craft::$app->language;
    }

    /** @inheritDoc */
    public function getId(): ?int
    {
        return null;
    }
}
