<?php

namespace webhubworks\verifiedentries\mail;

use Craft;
use craft\elements\User;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use webhubworks\verifiedentries\base\NotifiableInterface;
use webhubworks\verifiedentries\base\Notification;
use webhubworks\verifiedentries\models\ExpiredEntryData;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Sends a Reviewer a digest of expired entries assigned to them, prompting them to review them.
 */
class ExpiredNotification extends Notification
{
    /** @var ExpiredEntryData[] */
    protected array $entries = [];

    public function __construct(array $entries, NotifiableInterface $recipient, ?string $locale = null)
    {
        parent::__construct($recipient, $locale);
        $this->entries = $entries;
    }

    /** @inheritDoc */
    public function send(): bool
    {
        if (empty($this->entries)) {
            return false;
        }

        return Craft::$app->getMailer()->compose()
            ->setTo($this->recipient->getEmail())
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
            ['count' => count($this->entries)]
        );
    }

    private function body(): string
    {
        $recipientName = Html::encode($this->recipient->getFriendlyName());
        $intro = $this->t('The following entries have verification dates that have expired:');
        $viewAll = Html::a($this->t('View all'), $this->viewAllUrl());
        $listItems = implode('', array_map(fn($entry) => $this->listItem($entry), $this->entries));
        $styles = implode(';', $this->styles());

        return <<<HTML
            <div style="$styles">
                <p>Hi $recipientName,</p>
                <p>$intro</p>
                <p>$viewAll</p>
                <ol>$listItems</ol>
            </div>
            HTML;
    }

    private function listItem(ExpiredEntryData $entry): string
    {
        $title = Html::tag('strong', Html::encode($entry->title));
        $verifiedUntil = $this->t('Verified until');
        $verifiedUntilDate = $this->formatter->asDate($entry->verifiedUntilDate, Locale::LENGTH_MEDIUM);
        $link = Html::a($this->t('Edit', null, 'app'), $entry->getCpEditUrl());

        return Html::tag('li', sprintf(
            '"%s": %s %s. [%s]',
            $title,
            $verifiedUntil,
            $verifiedUntilDate,
            $link
        ));
    }

    private function viewAllUrl(): string
    {
        return UrlHelper::cpUrl('entries', [
            'source' => '*',
            'filters' => VerifiedEntries::getInstance()
                ->getVerification()
                ->getFilterParams($this->recipient->getId()),
        ]);
    }
}