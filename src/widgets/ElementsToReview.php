<?php

namespace webhubworks\verifiedelements\widgets;

use Craft;
use craft\base\Widget;
use craft\elements\Entry;
use craft\helpers\Cp;
use craft\helpers\Html;
use Throwable;
use webhubworks\verifiedelements\enums\VerificationStatus;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\Plugin;

/**
 * Expired Entries Widget widget type
 *
 * @property-read null|string $bodyHtml
 * @property-read null|string $settingsHtml
 */
class ElementsToReview extends Widget
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
        $enabledSectionIds = Plugin::getInstance()
            ->getPluginSettings()
            ->getEnabledContainerIds(Entry::class);

        /** @noinspection PhpUndefinedMethodInspection */
        $elements = Entry::find()
            ->status(Entry::STATUS_LIVE)
            ->site('*')
            ->sectionId($enabledSectionIds)
            ->reviewerId($userId)
            ->isVerified(false)
            ->limit($this->limit)
            ->all();

        if (empty($elements)) {
            return Html::tag(
                'div',
                Craft::t(Plugin::HANDLE, "There's currently nothing to review."),
                ['class' => ['zilch', 'small']]
            );
        }

        $templateVariables = [
            'elements' => $elements,
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
        }
        catch (Throwable $exception) {
            Log::error(
                sprintf('Error loading "%s" widget', self::NAME),
                $exception
            );
        }

        return null;
    }
}
