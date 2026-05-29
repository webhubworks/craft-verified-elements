<?php

namespace webhubworks\verifiedentries\base;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\i18n\Formatter;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\mail\ChangeNotification;
use webhubworks\verifiedentries\mail\ExpiredNotification;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * The base class for all notification classes.
 * @see ChangeNotification
 * @see ExpiredNotification
 */
abstract class Notification implements NotificationInterface
{
    /**
     * Yii/Craft's object for formatting dates and times by language/locales.
     *
     * @var Formatter
     */
    protected Formatter $formatter;

    public function __construct(
        protected User $recipient,
        protected ?string $locale = null,
    )
    {
        if (!$this->locale) {
            $this->locale = $this->recipient->getPreferredLocale();
        }

        if (!$this->locale) {
            $this->locale = Craft::$app->language;
        }

        $this->formatter = Craft::$app->getI18n()->getLocaleById($this->locale)->getFormatter();
    }

    /**
     * @param string $message
     * @param array|null $params
     * @param string $category
     * @return string
     */
    protected function t(string $message, ?array $params = null, string $category = VerifiedEntries::HANDLE): string
    {
        return Craft::t($category, $message, $params, $this->locale);
    }

    /**
     * Helper for ensuring important behaviors are attached to an entry.
     *
     * @param Entry $entry
     * @return Entry
     * @see VerifiableBehavior
     */
    protected function ensureBehavior(Entry $entry): Entry
    {
        if (!$entry->getBehavior(VerifiableBehavior::NAME)) {
            $entry->attachBehavior(VerifiableBehavior::NAME, VerifiableBehavior::class);
        }

        return $entry;
    }

    /**
     * CSS styles that can be inlined in the email's body to style the message.
     *
     * @return string[]
     */
    protected function styles(): array
    {
        return [
            'font-family:Helvetica, Arial, sans-serif',
            'font-size:16px',
            'line-height:1.4;',
        ];
    }
}