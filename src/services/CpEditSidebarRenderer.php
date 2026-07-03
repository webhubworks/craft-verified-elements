<?php

namespace webhubworks\verifiedelements\services;

use Craft;
use craft\base\Element;
use craft\helpers\Html;
use Throwable;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\services\singletons\PluginSettings;
use webhubworks\verifiedelements\Plugin;

/**
 * Constructs the HTML that gets injected into Craft's sidebar on an element's "edit" page,
 * exposing the verification fields provided by this plugin.
 *
 * @see src/templates/_sidebar.twig
 */
readonly class CpEditSidebarRenderer
{
    /** @var Element|VerifiableBehavior $element */

    public function __construct(
        private Element $element,
        private PluginSettings $settings
    ) {}

    /**
     * @return string The HTML to inject into Craft's sidebar.
     */
    public function buildHtml(): string
    {
        $html = '';

        if (! $this->element->getIsVerified()) {
            $html .= $this->buildWarningHtml();
        }

        $html .= $this->buildSidebarHtml();

        return $html;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * @return string The warning message for when an element has expired.
     */
    private function buildWarningHtml(): string
    {
        // Full sentences per element type (not one string with the label interpolated) so
        // translations stay grammatically correct.
        $text = match (ElementType::fromElement($this->element)) {
            ElementType::Entry => Craft::t(Plugin::HANDLE, 'Entry has expired and is due to be verified.'),
            ElementType::Asset => Craft::t(Plugin::HANDLE, 'Asset has expired and is due to be verified.'),
        };

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
        $elementType = ElementType::fromElement($this->element);

        $dateSelectOptions = VerificationFieldsRenderer::dateSelectOptions(
            $elementType->containerId($this->element),
            $this->element->siteId,
            $elementType->value,
            $this->settings,
            $this->element->getVerifiedUntilDate()
        );

        $templateVariables = [
            'addOptionFn' => VerificationFieldsRenderer::jsFunctionToAddCustomDate(),
            'verifiedUntilDate' => $this->element->getVerifiedUntilDate(),
            'isVerified' => $this->element->getIsVerified(),
            'reviewer' => $this->element->getReviewer(),
            'dateSelectOptions' => $dateSelectOptions,
        ];

        try {
            return Craft::$app->getView()->renderTemplate(
                Plugin::HANDLE . '/_sidebar.twig',
                $templateVariables
            );
        }
        catch (Throwable $exception) {
            Log::error(sprintf(
                'Error rendering sidebar HTML for %s',
                Log::element($this->element, plural: true, capitalize: false)
            ), $exception);
            return '';
        }
    }
}
