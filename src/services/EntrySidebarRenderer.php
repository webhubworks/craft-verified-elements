<?php

namespace webhubworks\verifiedentries\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\Html;
use Throwable;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Constructs the HTML that gets injected into Craft's sidebar on an entry's "edit" page,
 * exposing the verification fields provided by this plugin.
 *
 * @see src/templates/_sidebar.twig
 */
readonly class EntrySidebarRenderer
{
    /** @var Entry|VerifiableBehavior $entry */

    public function __construct(private Entry $entry) {}

    /**
     * @return string The HTML to inject into Craft's sidebar.
     */
    public function buildHtml(): string
    {
        $html = '';

        if (! $this->entry->getIsVerified()) {
            $html .= $this->buildWarningHtml();
        }

        $html .= $this->buildSidebarHtml();

        return $html;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * @return string The warning message for when an entry has expired.
     */
    private function buildWarningHtml(): string
    {
        $text = Craft::t(
            VerifiedEntries::HANDLE,
            'Entry has expired and is due to be verified.'
        );

        return Html::tag(
            'div',
            Html::tag('p', $text),
            ['class' => ['meta', 'warning']]
        );
    }

    /**
     * @return string
     * @see src/templates/_sidebar.twig
     */
    private function buildSidebarHtml(): string
    {
        $verification = VerifiedEntries::getInstance()->getVerification();

        $templateVariables = [
            'addOptionFn' => $verification->getAddOptionFn(),
            'verifiedUntilDate' => $this->entry->getVerifiedUntilDate(),
            'isVerified' => $this->entry->getIsVerified(),
            'reviewer' => $this->entry->getReviewer(),
            'options' => $verification->getDateOptionsForEntry(
                $this->entry->getVerifiedUntilDate(),
                $this->entry->sectionId,
                $this->entry->siteId
            ),
        ];

        try {
            return Craft::$app->getView()->renderTemplate(
                VerifiedEntries::HANDLE . '/_sidebar.twig',
                $templateVariables
            );
        }
        catch (Throwable $exception) {
            Craft::error(sprintf(
                "Error rendering sidebar HTML for entries: %s",
                $exception->getMessage()
            ), __METHOD__);
            return '';
        }
    }
}
