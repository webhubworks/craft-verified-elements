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
class EntriesToReview extends Widget
{
    public int $limit = 10;

    /** @inheritDoc */
    public static function displayName(): string
    {
        return Craft::t(Plugin::HANDLE, 'Entries to Review');
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
        $enabledSectionIds = Plugin::getInstance()->getPluginSettings()->getEnabledSectionIds();

        /** @noinspection PhpUndefinedMethodInspection */
        $entries = Entry::find()
            ->status(Entry::STATUS_LIVE)
            ->site('*')
            ->sectionId($enabledSectionIds)
            ->reviewerId($userId)
            ->isVerified(false)
            ->limit($this->limit)
            ->all();

        if (empty($entries)) {
            return Html::tag(
                'div',
                Craft::t(Plugin::HANDLE, 'There are no entries up for review.'),
                ['class' => ['zilch', 'small']]
            );
        }

        $templateVariables = [
            'entries' => $entries,
            'statusIndicator' => Cp::statusIndicatorHtml(
                VerificationStatus::Expired->handle(),
                ['color' => VerificationStatus::Expired->color()]
            ),
        ];

        try {
            return Craft::$app->getView()->renderTemplate(
                Plugin::HANDLE . '/_widgets/review.twig',
                $templateVariables
            );
        }
        catch (Throwable $exception) {
            Log::error('Error loading "Entries to Review" widget', $exception);
        }

        return null;
    }
}
