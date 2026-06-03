<?php

namespace webhubworks\verifiedentries;

use Craft;
use craft\base\Plugin;
use craft\helpers\UrlHelper;
use webhubworks\verifiedentries\enums\Permission;
use webhubworks\verifiedentries\events\EventRegistrar;
use webhubworks\verifiedentries\helpers\Log;
use webhubworks\verifiedentries\services\singletons\PluginSettings;
use webhubworks\verifiedentries\services\singletons\Reviewers;
use webhubworks\verifiedentries\services\singletons\Verification;

/**
 * Verified Entries plugin
 *
 * @method static VerifiedEntries getInstance()
 * @author webhubworks <support@webhub.de>
 * @copyright webhubworks
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read null|array $cpNavItem
 * @property-read null $settingsResponse
 * @property-read Reviewers $reviewers
 * @property-read PluginSettings $pluginSettings
 * @property-read Verification $verification
 */
class VerifiedEntries extends Plugin
{
    public const HANDLE = 'verified-entries';
    public string $schemaVersion = '1.0.0';
    public bool $hasCpSettings = true;
    public bool $hasCpSection = true;

    /** @inheritDoc */
    public static function config(): array
    {
        return [
            'components' => [
                'pluginSettings' => PluginSettings::class,
                'reviewers' => Reviewers::class,
                'verification' => Verification::class,
            ],
        ];
    }

    /** @inheritDoc */
    public function init(): void
    {
        parent::init();

        $this->name = Craft::t(self::HANDLE, 'Verified Entries');

        // Allow this plugin to log its errors in a dedicated log file called "verified-entries".
        Log::registerLogger();

        $request = Craft::$app->getRequest();
        $isCpRequest = $request->getIsCpRequest();
        $isConsoleRequest = $request->getIsConsoleRequest();

        // These event handlers must be registered before onInit()
        EventRegistrar::registerEarlyEvents($this, $isCpRequest, $isConsoleRequest);

        Craft::$app->onInit(function() use ($isCpRequest, $isConsoleRequest) {
            // Register the many event handlers.
            (new EventRegistrar(
                $this,
                $isCpRequest,
                $isConsoleRequest,
            ))->registerOnInitEvents();
        });
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
    public function getVerification(): Verification
    {
        return $this->get('verification');
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
