<?php

namespace webhubworks\verifiedelements;

use Craft;
use craft\base\Plugin as BasePlugin;
use craft\elements\Asset;
use craft\elements\db\AssetQuery;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\helpers\UrlHelper;
use webhubworks\verifiedelements\enums\Edition;
use webhubworks\verifiedelements\enums\Feature;
use webhubworks\verifiedelements\enums\Permission;
use webhubworks\verifiedelements\events\EventRegistrar;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\services\singletons\PluginSettings;
use webhubworks\verifiedelements\services\singletons\Reviewers;

/**
 * Verified Elements plugin
 *
 * @method static Plugin getInstance()
 * @author webhubworks <support@webhub.de>
 * @copyright webhubworks
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read null|array $cpNavItem
 * @property-read null $settingsResponse
 * @property-read Reviewers $reviewers
 * @property-read PluginSettings $pluginSettings
 */
class Plugin extends BasePlugin
{
    public const HANDLE = 'verified-elements';
    public string $schemaVersion = '2.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    /** @inheritDoc */
    public static function config(): array
    {
        return [
            'components' => [
                'pluginSettings' => PluginSettings::class,
                'reviewers' => Reviewers::class,
            ],
        ];
    }

    /** @inheritDoc */
    public function init(): void
    {
        parent::init();

        $this->name = Craft::t(self::HANDLE, 'Verified Elements');

        // Allow this plugin to log its errors in a dedicated log file called "verified-elements".
        Log::registerLogger();

        $request = Craft::$app->getRequest();

        $events = new EventRegistrar(
            $this,
            $request->getIsCpRequest(),
            $request->getIsConsoleRequest()
        );

        $events->registerEarlyEvents();

        Craft::$app->onInit(function() use ($events) {
            $events->registerCraftComponents();
            $events->extendTwig();

            if (Feature::EntryVerification->isEnabled()) {
                $events->registerBehaviors(Entry::class, EntryQuery::class);
                $events->registerEntryLifecycle();
                $events->registerEntryIndexUi();
                $events->registerEntryEditUi();
            }

            if (Feature::AssetVerification->isEnabled()) {
                $events->registerBehaviors(Asset::class, AssetQuery::class);
                $events->registerAssetLifecycle();
                $events->registerAssetEditUi();
            }
        });
    }

    /** @inheritDoc */
    public static function editions(): array
    {
        return Edition::currentlyAvailable();
    }

    /**
     * Use these methods to return our service Components and bypass Yii's magic method system.
     */
    public function getPluginSettings(): PluginSettings
    {
        return $this->get('pluginSettings');
    }
    public function getReviewers(): Reviewers
    {
        return $this->get('reviewers');
    }

    /** @inheritDoc */
    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $currentUser = Craft::$app->getUser();

        $nav['subnav']['overview'] = [
            'label' => Craft::t('app', 'Dashboard'),
            'url' => self::HANDLE,
        ];

        if ($currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::ManageVerificationSettings->value)) {
            $nav['subnav']['settings'] = [
                'label' => Craft::t('app', 'Settings'),
                'url' => self::HANDLE . '/settings',
            ];
        }

        return $nav;
    }

    /** @inheritDoc */
    public function getSettingsResponse(): null
    {
        // Redirect to our settings page
        Craft::$app->controller->redirect(
            UrlHelper::cpUrl(self::HANDLE . '/settings')
        );

        return null;
    }
}
