<?php /** @noinspection JSUnresolvedReference */

namespace webhubworks\verifiedelements\elements\actions;

use Craft;
use craft\base\Element;
use craft\base\ElementAction;
use DateTime;
use Throwable;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\Plugin;

/**
 * Bulk action that sets a new "Verified until" date on one or more elements from the element index
 * in the CP.
 *
 * @property-read null|string $triggerHtml
 */
class VerifyElement extends ElementAction
{
    public string|DateTime|null $date = null;

    /** @inheritDoc */
    public static function displayName(): string
    {
        return Craft::t(Plugin::HANDLE, 'Verify');
    }

    /** @inheritDoc */
    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type) => <<<JS
            (() => {
                new Craft.ElementActionTrigger({
                    type: $type,

                    // Whether this action should be available when multiple elements are selected
                    bulk: true,

                    // Return whether the action should be available depending on which elements are selected
                    validateSelection: (selectedItems, elementIndex) => {
                      return true;
                    },

                    activate: (selectedItems, elementIndex) => {
                      elementIndex.setIndexBusy();
                      
                      const modal = new Craft.CpModal('verified-elements/entries/request-period');
                      
                      modal.on('submit', ({response}) => {
                          elementIndex.submitAction($type, response.data);
                          elementIndex.setIndexAvailable();
                      });
                      
                      modal.on('close', () => {
                          elementIndex.setIndexAvailable();
                      })
                    },
                });
            })();
        JS, [static::class]);

        return null;
    }

    /** @inheritDoc */
    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $elements = $query->all();
        $elementsService = Craft::$app->getElements();

        $successCount = count(
            array_filter(
                $elements,
                function (Element $element) use ($elementsService) {
                    /** @var Element|VerifiableBehavior $element */
                    try {
                        $element->setVerifiedUntilDate($this->date);
                        $elementsService->saveElement($element);
                        return true;
                    } catch (Throwable) {
                        return false;
                    }
                }
            )
        );

        if ($successCount !== count($elements)) {
            $this->setMessage(Craft::t(Plugin::HANDLE, 'Could not verify all elements.'));
            return false;
        }

        $this->setMessage(Craft::t(Plugin::HANDLE, 'Elements verified.'));

        return true;
    }
}
