<?php

namespace webhubworks\verifiedelements\base;

use Craft;
use craft\i18n\Formatter;
use webhubworks\verifiedelements\mail\ChangeNotification;
use webhubworks\verifiedelements\mail\ExpiredNotification;
use webhubworks\verifiedelements\Plugin;

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
        protected NotifiableInterface $recipient,
        protected ?string $locale = null,
    ) {
        if (!$this->locale) {
            $this->locale = $this->recipient->getLocale();
        }

        $this->formatter = Craft::$app
            ->getI18n()
            ->getLocaleById($this->locale)
            ->getFormatter();
    }

    /**
     * @param string $message
     * @param array|null $params
     * @param string $category
     * @return string
     */
    protected function t(string $message, ?array $params = null, string $category = Plugin::HANDLE): string
    {
        return Craft::t($category, $message, $params, $this->locale);
    }

    /**
     * CSS styles that can be inlined in the email's body to style the message.
     *
     * @return string[]
     */
    protected function wrapperStyles(): array
    {
        return [
            'font-family:Helvetica, Arial, sans-serif',
            'font-size:16px',
            'line-height:1.4;',
        ];
    }

    /**
     * Returns the inline style declarations for one part of the digest table, keyed by name.
     *
     * @param string $key
     * @return string
     * @see Notification::parentStyles()
     */
    protected function tableStyle(string $key): string
    {
        $borderedCell = [
            'border:2px solid #d7d7d7',
            'padding:6px 8px',
            'text-align:left',
        ];

        $styles = [
            'table' => ['width:100%', 'border-collapse:collapse', 'color:#000000'],
            'headerCell' => [...$borderedCell, 'background-color:#ededed', 'color:#5c5c5c'],
            'numberHeaderCell' => [...$borderedCell, 'background-color:#ededed', 'width:30px'],
            'cell' => $borderedCell,
        ];

        return implode(';', $styles[$key]);
    }
}
