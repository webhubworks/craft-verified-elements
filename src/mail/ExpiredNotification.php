<?php

namespace webhubworks\verifiedelements\mail;

use Craft;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use webhubworks\verifiedelements\base\NotifiableInterface;
use webhubworks\verifiedelements\base\Notification;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\models\ElementData;
use webhubworks\verifiedelements\Plugin;

/**
 * Sends a Reviewer a digest of expired elements assigned to them, prompting them to review them.
 */
class ExpiredNotification extends Notification
{
    /** @var ElementData[] */
    protected array $elements = [];

    public function __construct(array $elements, NotifiableInterface $recipient, ?string $locale = null)
    {
        parent::__construct($recipient, $locale);
        $this->elements = $elements;
    }

    /** @inheritDoc */
    public function send(): bool
    {
        if (empty($this->elements)) {
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
            'email.expiredNotification.subject',
            ['count' => count($this->elements)]
        );
    }

    private function body(): string
    {
        $recipientName = Html::encode($this->recipient->getFriendlyName());
        $intro = $this->t('email.expiredNotification.body') . ':';
        $viewAll = Html::a($this->t('View all'), $this->viewAllUrl());
        $listItems = implode('', array_map(fn($elementData) => $this->listItem($elementData), $this->elements));
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

    private function listItem(ElementData $elementData): string
    {
        $title = Html::tag('strong', Html::encode($elementData->title));
        $verifiedUntil = $this->t('Verified until');
        $verifiedUntilDate = $this->formatter->asDate(
            DateHelper::toDateTime($elementData->verifiedUntilDate),
            Locale::LENGTH_MEDIUM
        );
        $link = Html::a($this->t('Edit', null, 'app'), $elementData->cpEditUrl);

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
            'filters' => Plugin::getInstance()
                ->getReviewers()
                ->getFilterParams($this->recipient->getId()),
        ]);
    }
}
