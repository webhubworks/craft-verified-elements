<?php /** @noinspection JSUnresolvedReference */

namespace webhubworks\verifiedentries\elements\actions;

use Craft;
use craft\base\ElementAction;
use craft\elements\Entry;
use Throwable;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Bulk action that assigns a Reviewer to one or more entries from the element index in the CP.
 *
 * @property-read null|string $triggerHtml
 */
class AssignReviewer extends ElementAction
{
    public ?int $reviewerId = null;

    /** @inheritDoc */
    public static function displayName(): string
    {
        return Craft::t(VerifiedEntries::HANDLE, 'Assign Reviewer');
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
                      
                      Craft.createElementSelectorModal('craft\\\\elements\\\\User', {
                          multiSelect: false,
                          criteria: {
                              'status': 'active',
                              'can': 'verified-entries:verifyEntries', // this value must match what's in the Permission enum
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
        JS, [static::class]);

        return null;
    }

    /** @inheritDoc */
    public function performAction(Craft\elements\db\ElementQueryInterface $query): bool
    {
        $elements = $query->all();
        $elementsService = Craft::$app->getElements();

        $savedEntries = array_filter(
            $elements,
            function (Entry $entry) use ($elementsService) {
                try {
                    /** @var Entry|VerifiableBehavior $entry */
                    $entry->setReviewerId($this->reviewerId);
                    $elementsService->saveElement($entry);
                    return true;
                } catch (Throwable) {
                    return false;
                }
            }
        );

        if (count($savedEntries) !== count($elements)) {
            $this->setMessage('Could not assign Reviewer to all entries.');
            return false;
        }

        $this->setMessage('Entries assigned.');
        return true;
    }
}
