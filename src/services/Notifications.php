<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\UrlHelper;
use craft\i18n\Formatter;
use craft\i18n\Locale;
use Illuminate\Support\Collection;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\VerifiedEntries;
use yii\base\Component;
use yii\helpers\Markdown;

/**
 * The Notifications service represents logic related to notifying Reviewers about entries
 * assigned to them.
 */
class Notifications extends Component
{
    /**
     * Send a Reviewer a digest of expired entries assigned to them, prompting them to review them.
     *
     * @param User $reviewer
     * @param array|Collection $entries
     * @return void
     */
    public function sendExpiredNotification(User $reviewer, array|Collection $entries): void
    {
        if (empty($entries)) {
            return;
        }

        $language = $reviewer->getPreferredLanguage();
        $formatter = $this->getFormatter($language);

        $subject = Craft::t(
            VerifiedEntries::HANDLE,
            '{count, number} {count, plural, =1{entry awaits} other{entries await}} your verification',
            ['count' => count($entries)],
            $language
        );

        $body = 'Hi ' . $reviewer->getFriendlyName() . ",\n\n";

        $body .= Craft::t(
            VerifiedEntries::HANDLE,
            'the following entries have verification dates that have expired:',
            null,
            $language,
        ) . "\n\n";

        $linkText = Craft::t(
            VerifiedEntries::HANDLE,
            'View all',
            null,
            $language
        );

        $cpEditUrl = UrlHelper::cpUrl('entries', [
            // 'site' will automatically be set by Craft to $reviewer's preferred site.
            'source' => '*',
            'filters' => VerifiedEntries::getInstance()
                ->getVerification()
                ->getFilterParams($reviewer->id),
        ]);

        $body .= "[$linkText]($cpEditUrl)\n\n";

        $body .= '<ol>';
        foreach ($entries as $entry) {
            $verifiedUntilText = Craft::t(
                VerifiedEntries::HANDLE,
                'Verified until',
                null,
                $language
            );

            $verifiedUntilDate = $formatter->asDate(
                $entry['verifiedUntilDate'],
                Locale::LENGTH_MEDIUM
            );

            $linkText = Craft::t('app', 'Edit', null, $language);

            $cpEditUrl = UrlHelper::cpUrl(
                "entries/{$entry['sectionHandle']}/{$entry['entryId']}",
                ['site' => $entry['siteHandle']]
            );

            $body .= "<li>**{$entry['title']}** ($verifiedUntilText $verifiedUntilDate) [$linkText]($cpEditUrl)\n</li>";
        }
        $body .= '</ol>';

        Craft::$app->getMailer()->compose()
            ->setTo($reviewer->email)
            ->setSubject($subject)
            ->setHtmlBody(Markdown::process($body))
            ->send();

    }

    /**
     * Send an entry's Reviewer an email that someone has updated their entry
     * and that they should verify the changes.
     *
     * @param Entry $entry
     * @param User $reviewer
     * @return void
     */
    public function sendChangeNotification(Entry $entry, User $reviewer): void
    {
        /** @var Entry|VerifiableBehavior $entry */

        $language = $reviewer->getPreferredLanguage();
        $formatter = $this->getFormatter($language);

        $sectionHandle = $entry->getSection()->handle;

        $cpEditUrl = UrlHelper::cpUrl(
            "entries/$sectionHandle/$entry->id",
            ['site' => $entry->getSite()->handle]
        );

        $subject = Craft::t(
            VerifiedEntries::HANDLE,
            'Entry has been updated',
            null,
            $language
        );

        $greeting = 'Hi ' . $reviewer->getFriendlyName() . ',';

        $message = Craft::t(
            VerifiedEntries::HANDLE,
            "An entry you're assigned to review has been updated. Please take a moment to review the latest changes:",
            null,
            $language
        );

        $verifiedUntil = Craft::t(
            VerifiedEntries::HANDLE,
            'Verified until',
            null,
            $language
        );

        $verifiedUntilDate = $formatter->asDate(
            $entry->getVerifiedUntilDate(),
            Locale::LENGTH_MEDIUM
        );

        $linkText = Craft::t('app', 'Show', null, $language);

        $body = "$greeting\n\n";
        $body .= "$message\n\n";
        $body .= "**$entry->title**<br>";
        $body .= "$verifiedUntil $verifiedUntilDate\n\n";
        $body .= "[$linkText]($cpEditUrl)";

        Craft::$app->getMailer()->compose()
            ->setTo($reviewer->email)
            ->setSubject($subject)
            ->setHtmlBody(Markdown::process($body))
            ->send();
    }


    // HELPERS
    // =============================================================================================

    /**
     * Return Yii/Craft's object for formatting dates and times by language/locales.
     *
     * @param string|null $locale
     * @return Formatter
     */
    private function getFormatter(string|null $locale): Formatter
    {
        return $locale
            ? Craft::$app->getI18n()->getLocaleById($locale)->getFormatter()
            : Craft::$app->getFormatter();
    }
}
