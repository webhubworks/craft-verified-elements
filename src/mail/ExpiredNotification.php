<?php

namespace webhubworks\verifiedelements\mail;

use Craft;
use craft\helpers\Html;
use craft\helpers\UrlHelper;
use craft\i18n\Locale;
use webhubworks\verifiedelements\base\NotifiableInterface;
use webhubworks\verifiedelements\base\Notification;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\ReviewerStatus;
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
        $sections = implode('', array_map(
            fn(ElementType $elementType) => $this->typeSection($elementType),
            $this->elementTypesInDigest()
        ));
        $styles = implode(';', $this->wrapperStyles());

        return <<<HTML
            <div style="$styles">
                <p>Hi $recipientName,</p>
                <p>$intro</p>
                $sections
            </div>
            HTML;
    }

    /**
     * Returns the element types present in this digest, in the enum's case order so sections
     * render in a consistent order across digests.
     *
     * @return ElementType[]
     */
    private function elementTypesInDigest(): array
    {
        $typesPresent = array_unique(array_map(
            static fn(ElementData $elementData) => $elementData->type,
            $this->elements
        ));

        return array_filter(
            ElementType::cases(),
            static fn(ElementType $elementType) => in_array($elementType->value, $typesPresent, true)
        );
    }

    /**
     * Renders one element type's digest section: a heading with a "View all" link to that type's
     * index in the plugin's CP section, followed by a table of the expired elements of that type.
     *
     * @param ElementType $elementType
     * @return string
     */
    private function typeSection(ElementType $elementType): string
    {
        $viewAll = Html::a($this->t('View all'), $this->viewAllUrl($elementType));
        $heading = Html::tag('h2', sprintf(
            '%s <span style="font-size:18px;">[%s]</span>',
            $this->t($elementType->label(plural: true), null, 'app'),
            $viewAll
        ));

        $elementsOfType = array_values(array_filter(
            $this->elements,
            static fn(ElementData $elementData) => $elementData->type === $elementType->value
        ));

        return $heading . $this->elementsTable($elementsOfType);
    }

    /**
     * Renders a full-width table of expired elements.
     *
     * @param ElementData[] $elements
     * @return string
     */
    private function elementsTable(array $elements): string
    {
        $headerCells = Html::tag('th', '#', ['style' => $this->tableStyle('numberHeaderCell')]);
        $headerCells .= implode('', array_map(
            fn(string $label) => Html::tag('th', $label, ['style' => $this->tableStyle('headerCell')]),
            [
                $this->t('Title', null, 'app'),
                $this->t('Verified until'),
                $this->t('Link', null, 'app'),
            ]
        ));

        $rows = implode('', array_map(
            fn(ElementData $elementData, int $index) => $this->tableRow($elementData, $index + 1),
            $elements,
            array_keys($elements)
        ));

        return Html::tag(
            'table',
            Html::tag('thead', Html::tag('tr', $headerCells)) . Html::tag('tbody', $rows),
            ['style' => $this->tableStyle('table')]
        );
    }

    /**
     * Renders one expired element as a table row.
     *
     * @param ElementData $elementData
     * @param int $rowNumber 1-indexed position in the table
     * @return string
     */
    private function tableRow(ElementData $elementData, int $rowNumber): string
    {
        $title = Html::encode($elementData->title);
        $verifiedUntilDate = $this->formatter->asDate(
            DateHelper::toDateTime($elementData->verifiedUntilDate),
            Locale::LENGTH_MEDIUM
        );
        $link = Html::a($this->t('Edit', null, 'app'), $elementData->cpEditUrl);

        $cells = implode('', array_map(
            fn(string $content) => Html::tag('td', $content, ['style' => $this->tableStyle('cell')]),
            [(string)$rowNumber, $title, $verifiedUntilDate, $link]
        ));

        return Html::tag('tr', $cells);
    }

    private function viewAllUrl(ElementType $elementType): string
    {
        // Reviewers land on their own assigned elements; the system recipient (no user ID)
        // handles the unassigned ones.
        $source = $this->recipient->getId() ? 'mine' : ReviewerStatus::Unassigned->handle();

        return UrlHelper::cpUrl(
            Plugin::HANDLE . '/' . $elementType->uriSegment(),
            ['source' => $source]
        );
    }
}
