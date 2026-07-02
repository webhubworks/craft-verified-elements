<?php

namespace webhubworks\verifiedelements\mail;

use Craft;
use craft\helpers\Html;
use craft\i18n\Locale;
use webhubworks\verifiedelements\base\NotifiableInterface;
use webhubworks\verifiedelements\base\Notification;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\models\ElementData;

/**
 * Sends an entry's Reviewer an email that someone has updated their entry
 * and that they should verify the changes.
 */
class ChangeNotification extends Notification
{
    protected ElementData|null $elementData = null;

    public function __construct(ElementData $elementData, NotifiableInterface $recipient, ?string $locale = null)
    {
        parent::__construct($recipient, $locale);

        $this->elementData = $elementData;
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
            DateHelper::toDateTime($this->elementData->verifiedUntilDate),
            Locale::LENGTH_MEDIUM
        );

        $recipientName = Html::encode($this->recipient->getFriendlyName());
        $message = $this->t('email.changeNotification.body') . ':';
        $title = Html::tag('strong', Html::encode($this->elementData->title));
        $verifiedUntil = $this->t('Verified until');
        $link = Html::a($this->t('Show', null, 'app'), $this->elementData->cpEditUrl);
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
