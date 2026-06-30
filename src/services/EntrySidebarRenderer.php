<?php

namespace webhubworks\verifiedelements\services;

use Craft;
use craft\elements\Entry;
use craft\helpers\Html;
use Throwable;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\services\singletons\PluginSettings;
use webhubworks\verifiedelements\Plugin;

/**
 * Constructs the HTML that gets injected into Craft's sidebar on an entry's "edit" page,
 * exposing the verification fields provided by this plugin.
 *
 * @see src/templates/_sidebar.twig
 */
readonly class EntrySidebarRenderer
{
    /** @var Entry|VerifiableBehavior $entry */

    public function __construct(
        private Entry $entry,
        private PluginSettings $settings
    ) {}

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
            Plugin::HANDLE,
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
        $dateSelectOptions = VerificationFieldsRenderer::dateSelectOptions(
            $this->entry->sectionId,
            $this->entry->siteId,
            $this->settings,
            $this->entry->getVerifiedUntilDate()
        );

        $templateVariables = [
            'addOptionFn' => VerificationFieldsRenderer::jsFunctionToAddCustomDate(),
            'verifiedUntilDate' => $this->entry->getVerifiedUntilDate(),
            'isVerified' => $this->entry->getIsVerified(),
            'reviewer' => $this->entry->getReviewer(),
            'dateSelectOptions' => $dateSelectOptions,
        ];

        try {
            return Craft::$app->getView()->renderTemplate(
                Plugin::HANDLE . '/_sidebar.twig',
                $templateVariables
            );
        }
        catch (Throwable $exception) {
            Log::error('Error rendering sidebar HTML for entries', $exception);
            return '';
        }
    }
}
