<?php

namespace webhubworks\verifiedelements\base;

/**
 * Interface for recipients receiving this plugin's notifications.
 */
interface NotifiableInterface
{
    /** @return string The recipient's email */
    public function getEmail(): string;

    /** @return string The recipient's full name */
    public function getName(): string;

    /** @return string The recipient's friendly name */
    public function getFriendlyName(): string;

    /** @return string|null The recipient's language/locale */
    public function getLocale(): ?string;

    /** @return int|null The recipient's User ID */
    public function getId(): ?int;
}
