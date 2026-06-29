<?php

namespace webhubworks\verifiedentries\mail;

use Craft;
use craft\elements\Entry;
use craft\helpers\Html;
use craft\i18n\Locale;
use webhubworks\verifiedentries\base\NotifiableInterface;
use webhubworks\verifiedentries\base\Notification;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;

/**
 * Sends an entry's Reviewer an email that someone has updated their entry
 * and that they should verify the changes.
 */
class ChangeNotification extends Notification
{
    protected Entry|VerifiableBehavior|null $entry = null;

    public function __construct(Entry $entry, NotifiableInterface $recipient, ?string $locale = null)
    {
        parent::__construct($recipient, $locale);

        $this->entry = $this->ensureBehavior($entry);
    }

    /** @inheritDoc */
    public function send(): bool
    {
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
        return $this->t('email.changeNotification.subject');
    }

    private function body(): string
    {
        $verifiedUntilDate = $this->formatter->asDate(
            $this->entry->getVerifiedUntilDate(),
            Locale::LENGTH_MEDIUM
        );

        $recipientName = Html::encode($this->recipient->getFriendlyName());
        $message = $this->t('email.changeNotification.body') . ':';
        $title = Html::tag('strong', Html::encode($this->entry->title));
        $verifiedUntil = $this->t('Verified until');
        $link = Html::a($this->t('Show', null, 'app'), $this->entry->getCpEditUrl());
        $styles = implode(';', $this->styles());

        return <<<HTML
            <div style="$styles">
                <p>Hi $recipientName,</p>
                <p>$message</p>
                <p>"$title"<br>$verifiedUntil $verifiedUntilDate</p>
                <p>$link</p>
            </div>
            HTML;
    }
}
