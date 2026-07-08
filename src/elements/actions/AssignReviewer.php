<?php /** @noinspection JSUnresolvedReference */

namespace webhubworks\verifiedelements\elements\actions;

use Craft;
use craft\base\Element;
use craft\base\ElementAction;
use Throwable;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\Plugin;

/**
 * Bulk action that assigns a Reviewer to one or more elements from the element index in the CP.
 *
 * @property-read null|string $triggerHtml
 */
class AssignReviewer extends ElementAction
{
    public ?int $reviewerId = null;

    /** @inheritDoc */
    public static function displayName(): string
    {
        return Craft::t(Plugin::HANDLE, 'Assign Reviewer');
    }

    /** @inheritDoc */
    public function getTriggerHtml(): ?string
    {
        Craft::$app->getView()->registerJsWithVars(fn($type, $canPermission) => <<<JS
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

                      Craft.createElementSelectorModal('craft\\\\elements\\\\User', {
                          multiSelect: false,
                          criteria: {
                              'status': 'active',
                              'can': $canPermission,
                          },
                          onSelect: ([user]) => {
                              elementIndex.submitAction($type, { reviewerId: user.id })
                          },
                          onHide: () => {
                              elementIndex.setIndexAvailable();
                          }
                      })
                    },
                });
            })();
        JS, [
            static::class,
            ElementType::fromElementClass($this->elementType)->verifyPermission()->value
        ]);

        return null;
    }

    /** @inheritDoc */
    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $elements = $query->all();
        $elementsService = Craft::$app->getElements();

        $savedElements = array_filter(
            $elements,
            function (Element $element) use ($elementsService) {
                try {
                    /** @var Element|VerifiableBehavior $element */
                    $element->setReviewerId($this->reviewerId);
                    $elementsService->saveElement($element);
                    return true;
                } catch (Throwable) {
                    return false;
                }
            }
        );

        if (count($savedElements) !== count($elements)) {
            $this->setMessage(Craft::t(Plugin::HANDLE, 'Could not assign a Reviewer to all elements.'));
            return false;
        }

        $this->setMessage(Craft::t(Plugin::HANDLE, 'Elements assigned.'));
        return true;
    }
}
