<?php

namespace webhubworks\verifiedentries\mail;

use Craft;
use craft\elements\User;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use webhubworks\verifiedentries\base\Notification;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Sends a Reviewer a digest of expired entries assigned to them, prompting them to review them.
 */
class ExpiredNotification extends Notification
{
    protected array $entryData = [];

    public function __construct(array $entryData, User $recipient, ?string $locale = null)
    {
        parent::__construct($recipient, $locale);
        $this->entryData = $entryData;
    }

    /** @inheritDoc */
    public function send(): bool
    {
        if (empty($this->entryData)) {
            return false;
        }

        return Craft::$app->getMailer()->compose()
            ->setTo($this->recipient->email)
            ->setSubject($this->subject())
            ->setHtmlBody($this->body())
            ->send();
    }


    // PRIVATE HELPERS
    // =============================================================================================

    private function subject(): string
    {
        return $this->t(
            '{count, number} {count, plural, =1{entry awaits} other{entries await}} your verification',
            ['count' => count($this->entryData)]
        );
    }

    private function body(): string
    {
        $recipientName = Html::encode($this->recipient->getFriendlyName());
        $intro = $this->t('The following entries have verification dates that have expired:');
        $viewAll = Html::a($this->t('View all'), $this->viewAllUrl());
        $listItems = implode('', array_map(fn($entry) => $this->listItem($entry), $this->entryData));

        return <<<HTML
            <p>Hi $recipientName,</p>
            <p>$intro</p>
            <p>$viewAll</p>
            <ol>$listItems</ol>
            HTML;
    }

    private function listItem(array $entryArray): string
    {
        $title = Html::tag('strong', Html::encode($entryArray['title']));
        $verifiedUntil = $this->t('Verified until');
        $verifiedUntilDate = $this->formatter->asDate($entryArray['verifiedUntilDate'], Locale::LENGTH_MEDIUM);
        $link = Html::a($this->t('Edit', null, 'app'), UrlHelper::cpUrl(
            "entries/{$entryArray['sectionHandle']}/{$entryArray['entryId']}",
            ['site' => $entryArray['siteHandle']]
        ));

        return Html::tag('li', "$title ($verifiedUntil $verifiedUntilDate) $link");
    }

    private function viewAllUrl(): string
    {
        return UrlHelper::cpUrl('entries', [
            'source' => '*',
            'filters' => VerifiedEntries::getInstance()
                ->getVerification()
                ->getFilterParams($this->recipient->id),
        ]);
    }
}