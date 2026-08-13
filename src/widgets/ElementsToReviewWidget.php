<?php

namespace webhubworks\verifiedelements\widgets;

use Craft;
use craft\base\Widget;
use craft\helpers\Cp;
use craft\helpers\Html;
use Throwable;
use webhubworks\verifiedelements\db\PluginQuery;
use webhubworks\verifiedelements\enums\ElementType;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\models\ElementData;
use webhubworks\verifiedelements\Plugin;

/**
 * Craft dashboard widget that lists expired elements assigned to the current user for review,
 * most overdue first.
 *
 * @property-read null|string $bodyHtml
 * @property-read null|string $settingsHtml
 */
class ElementsToReviewWidget extends Widget
{
    public const NAME = 'Elements to Review';

    public int $limit = 10;

    /** @inheritDoc */
    public static function displayName(): string
    {
        return Craft::t(Plugin::HANDLE, self::NAME);
    }

    /** @inheritDoc */
    protected static function allowMultipleInstances(): bool
    {
        return false;
    }

    /** @inheritDoc */
    public static function isSelectable(): bool
    {
        return true;
    }

    /** @inheritDoc */
    public static function icon(): ?string
    {
        return 'badge-check';
    }

    /** @inheritDoc */
    protected function defineRules(): array
    {
        $rules = parent::defineRules();
        $rules[] = [['limit'], 'number', 'integerOnly' => true];
        return $rules;
    }

    /** @inheritDoc */
    public function getSettingsHtml(): ?string
    {
        return Cp::textFieldHtml([
            'label' => Craft::t('app', 'Limit'),
            'id' => 'limit',
            'name' => 'limit',
            'value' => $this->limit,
            'size' => 2,
            'errors' => $this->getErrors('limit'),
        ]);
    }

    /** @inheritDoc */
    public function getBodyHtml(): ?string
    {
        $userId = Craft::$app->getUser()->getId();
        $inScopeSiteIds = Plugin::getInstance()->getPluginSettings()->getInScopeSiteIds();

        $rows = PluginQuery::expiredVerifiableElements(ElementType::enabledTypes(), $inScopeSiteIds, $userId)
            ->orderBy(['verifiedUntilDate' => SORT_ASC, 'title' => SORT_ASC])
            ->limit($this->limit)
            ->all();

        if (empty($rows)) {
            return Html::tag(
                'div',
                Craft::t(Plugin::HANDLE, "There's currently nothing to review."),
                ['class' => ['zilch', 'small']]
            );
        }

        $templateVariables = [
            'elements' => array_map(
                static fn(array $row) => ElementData::fromArray($row),
                $rows
            ),
            'typeLabels' => self::typeLabels(),
            'statusIndicator' => Cp::statusIndicatorHtml(
                VerificationStatus::Expired->handle(),
                ['color' => VerificationStatus::Expired->color()]
            ),
        ];

        try {
            return Craft::$app->getView()->renderTemplate(
                Plugin::HANDLE . '/_widgets/elements-to-review.twig',
                $templateVariables
            );
        } catch (Throwable $exception) {
            Log::error(
                sprintf('Error loading "%s" widget', self::NAME),
                $exception
            );
        }

        return null;
    }


    // PRIVATE HELPERS
    // =============================================================================================

    /**
     * Maps element class names to translated display labels for the template's "Type" column.
     *
     * @return array<string, string>
     */
    private static function typeLabels(): array
    {
        $labels = [];
        foreach (ElementType::enabledTypes() as $elementTypeName) {
            /** @var class-string<\craft\base\Element> $elementTypeName */
            $labels[$elementTypeName] = $elementTypeName::displayName();
        }

        return $labels;
    }
}
