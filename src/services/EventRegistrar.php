<?php

namespace webhubworks\verifiedelements\services;

use Craft;
use craft\base\conditions\BaseCondition;
use craft\base\Element;
use craft\base\Event;
use craft\base\Model;
use craft\controllers\UsersController;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Asset;
use craft\elements\conditions\assets\AssetCondition;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\db\AssetQuery;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\events\DefineAttributeHtmlEvent;
use craft\events\DefineBehaviorsEvent;
use craft\events\DefineEditUserScreensEvent;
use craft\events\DefineHtmlEvent;
use craft\events\DefineMetadataEvent;
use craft\events\DefineRulesEvent;
use craft\events\ModelEvent;
use craft\events\RegisterComponentTypesEvent;
use craft\events\RegisterConditionRulesEvent;
use craft\events\RegisterElementActionsEvent;
use craft\events\RegisterElementSortOptionsEvent;
use craft\events\RegisterElementTableAttributesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\events\RegisterUserPermissionsEvent;
use craft\helpers\Cp;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\log\Dispatcher;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\validators\DateTimeValidator;
use craft\web\UrlManager;
use DateTime;
use Twig\TwigFilter;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedelements\elements\actions\AssignReviewer;
use webhubworks\verifiedelements\elements\actions\VerifyElement;
use webhubworks\verifiedelements\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedelements\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedelements\elements\conditions\VerifiedUntilDateConditionRule;
use webhubworks\verifiedelements\elements\VerifiedAsset;
use webhubworks\verifiedelements\elements\VerifiedEntry;
use webhubworks\verifiedelements\enums\Feature;
use webhubworks\verifiedelements\enums\Permission;
use webhubworks\verifiedelements\helpers\AssetHelper;
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\models\ElementData;
use webhubworks\verifiedelements\models\SystemRecipient;
use webhubworks\verifiedelements\models\UserRecipient;
use webhubworks\verifiedelements\Plugin;
use webhubworks\verifiedelements\widgets\ElementsToReviewWidget;
use webhubworks\verifiedelements\widgets\VerificationHealthWidget;
use yii\base\Exception;

/**
 * Helper class for registering the plugin's event handlers.
 * @see Plugin::init()
 */
readonly class EventRegistrar
{
    public function __construct(
        private Plugin $plugin,
        private bool   $isCpRequest,
        private bool   $isConsoleRequest,
    ) {}


    // PLUGIN EARLY INIT events
    // =============================================================================================

    /**
     * Events that must run before Craft::$app->onInit() runs.
     *
     * @return void
     * @see BaseCondition::EVENT_REGISTER_CONDITION_RULES
     * @see Gc::EVENT_RUN
     * @see UrlManager::EVENT_REGISTER_CP_URL_RULES
     * @see Plugin::init()
     */
    public function registerPluginEarlyInitEvents(): void
    {
        if (! $this->isCpRequest && ! $this->isConsoleRequest) {
            return;
        }

        if (Feature::EntryVerification->isEnabled()) {
            Event::on(
                EntryCondition::class,
                BaseCondition::EVENT_REGISTER_CONDITION_RULES,
                static function (RegisterConditionRulesEvent $event) {
                    $event->conditionRules[] = VerifiedConditionRule::class;
                    $event->conditionRules[] = VerifiedUntilDateConditionRule::class;
                    $event->conditionRules[] = ReviewerConditionRule::class;
                }
            );
        }

        if (Feature::AssetVerification->isEnabled()) {
            Event::on(
                AssetCondition::class,
                BaseCondition::EVENT_REGISTER_CONDITION_RULES,
                static function (RegisterConditionRulesEvent $event) {
                    $event->conditionRules[] = VerifiedConditionRule::class;
                    $event->conditionRules[] = VerifiedUntilDateConditionRule::class;
                    $event->conditionRules[] = ReviewerConditionRule::class;
                }
            );
        }

        Event::on(Gc::class, Gc::EVENT_RUN, $this->onRunGarbageCollection(...));

        if (! $this->isCpRequest) {
            return;
        }

        Event::on(UrlManager::class, UrlManager::EVENT_REGISTER_CP_URL_RULES, $this->onRegisterCpUrlRules(...));
    }

    /**
     * Emails each Reviewer a digest of their expired elements, and the system admin a digest
     * of expired elements that have no Reviewer.
     *
     * @return void
     * @see Gc::EVENT_RUN
     * @see registerPluginEarlyInitEvents()
     */
    protected function onRunGarbageCollection(): void
    {
        $service = new ExpiredVerificationNotifier(Dispatcher::TARGET_WEB);

        foreach ($service->getExpiredElementsByReviewer() as $reviewerId => $expiredElements) {

            // 1. Find the Reviewer
            if (! $reviewer = $service->getReviewer($reviewerId)) {
                Log::warning(
                    "Reviewer $reviewerId not found or inactive. Skipping expired notification.",
                    __METHOD__
                );
                $service->reassignElementsToUnassigned($reviewerId);
                continue;
            }

            // 2. Notify the Reviewer
            $isSent = $service->notifyRecipient(
                new UserRecipient($reviewer),
                $expiredElements
            );

            if (! $isSent) {
                Log::warning(
                    "Failed to send expired notification to User $reviewer->id.",
                    __METHOD__
                );
            }
        }

        if (! $service->hasUnassignedExpiredElements()) {
            return;
        }

        // 1. Notify the system admin
        $recipient = new SystemRecipient();
        $isSent = $service->notifyRecipient(
            $recipient,
            $service->getUnassignedExpiredElements()
        );

        if (! $isSent) {
            Log::warning(
                "Failed to send expired notification to " . $recipient->getEmail() . '.',
                __METHOD__
            );
        }
    }

    /**
     * Registers the plugin's CP URL rules: the dashboard pages, the settings subpages, and the
     * per-user account screens. Feature- and permission-gated.
     *
     * @param RegisterUrlRulesEvent $event
     * @return void
     * @see UrlManager::EVENT_REGISTER_CP_URL_RULES
     * @see registerPluginEarlyInitEvents()
     */
    protected function onRegisterCpUrlRules(RegisterUrlRulesEvent $event): void
    {
        $currentUser = Craft::$app->getUser();

        $event->rules[Plugin::HANDLE] = Plugin::HANDLE . '/index/index';

        // Show the plugin's "Entries" page in the CP. The bare plugin handle is the
        // landing page for the plugin's top-level nav link and renders the same page.
        if (Feature::EntryVerification->isEnabled()) {
            $event->rules[Plugin::HANDLE . '/entries'] = Plugin::HANDLE . '/index/entries';
        }

        // Show the plugin's "Assets" page in the CP
        if (Feature::AssetVerification->isEnabled()) {
            $event->rules[Plugin::HANDLE . '/assets'] = Plugin::HANDLE . '/index/assets';
        }

        // Expose the plugin's settings subpages. The bare settings path lands on the
        // first subpage (entries), mirroring the plugin's top-level nav link.
        if ($currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::ManageVerificationSettings->value)) {
            $event->rules[Plugin::HANDLE . '/settings'] = Plugin::HANDLE . '/settings/entries';
            $event->rules[Plugin::HANDLE . '/settings/entries'] = Plugin::HANDLE . '/settings/entries';
            $event->rules[Plugin::HANDLE . '/settings/subscription-plan'] = Plugin::HANDLE . '/settings/subscription-plan';

            if (Feature::AssetVerification->isEnabled()) {
                $event->rules[Plugin::HANDLE . '/settings/assets'] = Plugin::HANDLE . '/settings/assets';
            }
        }

        // Current user's account "Verified Elements" page showing their assigned elements to review
        $event->rules['myaccount/' . Plugin::HANDLE] = Plugin::HANDLE . '/reviewers/index';
        $event->rules['users/<userId:\d+>/' . Plugin::HANDLE] = Plugin::HANDLE . '/reviewers/index';
    }


    // PLUGIN READY events
    // =============================================================================================

    /**
     * Events that register plugin features into Craft's various systems.
     *
     * @return void
     * @see Elements::EVENT_REGISTER_ELEMENT_TYPES
     * @see UserPermissions::EVENT_REGISTER_PERMISSIONS
     * @see Dashboard::EVENT_REGISTER_WIDGET_TYPES
     * @see UsersController::EVENT_DEFINE_EDIT_SCREENS
     * @see Plugin::init()
     */
    public function registerPluginReadyEvents(): void
    {
        if (! $this->isCpRequest && ! $this->isConsoleRequest) {
            return;
        }

        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, $this->onRegisterElementTypes(...));

        if (! $this->isCpRequest) {
            return;
        }

        Event::on(UserPermissions::class, UserPermissions::EVENT_REGISTER_PERMISSIONS, $this->onRegisterUserPermissions(...));
        Event::on(Dashboard::class, Dashboard::EVENT_REGISTER_WIDGET_TYPES, $this->onRegisterWidgets(...));
        Event::on(UsersController::class, UsersController::EVENT_DEFINE_EDIT_SCREENS, $this->onDefineEditScreens(...));
    }

    /**
     * Extend Twig for the plugin's needs.
     *
     * @return void
     * @see Plugin::init()
     */
    public function extendTwig(): void
    {
        // define Twig constants
        Craft::$app->getView()->getTwig()->addGlobal('pluginHandle', Plugin::HANDLE);

        // define custom Twig functions
        Craft::$app->getView()->getTwig()->addFilter(
            new TwigFilter(
                'readableVerificationDate',
                fn(?DateTime $date): string => DateHelper::readableVerificationDate($date)
            )
        );
    }

    /**
     * Registers the plugin's element types (the dashboard index subtypes) per enabled feature.
     *
     * @param RegisterComponentTypesEvent $event
     * @return void
     * @see Elements::EVENT_REGISTER_ELEMENT_TYPES
     * @see registerPluginReadyEvents()
     */
    protected function onRegisterElementTypes(RegisterComponentTypesEvent $event): void
    {
        if (Feature::EntryVerification->isEnabled()) {
            $event->types[] = VerifiedEntry::class;
        }

        if (Feature::AssetVerification->isEnabled()) {
            $event->types[] = VerifiedAsset::class;
        }
    }

    /**
     * Adds the plugin's permission group to Craft's user permissions.
     *
     * @param RegisterUserPermissionsEvent $event
     * @return void
     * @see UserPermissions::EVENT_REGISTER_PERMISSIONS
     * @see registerPluginReadyEvents()
     */
    protected function onRegisterUserPermissions(RegisterUserPermissionsEvent $event): void
    {
        $event->permissions[] = [
            'heading' => Craft::t(Plugin::HANDLE, 'Verified Elements'),
            'permissions' => [
                Permission::ManageVerificationSettings->value => [
                    'label' => Craft::t(Plugin::HANDLE, 'Manage Verification Settings'),
                ],
                Permission::VerifyEntries->value => [
                    'label' => Craft::t(Plugin::HANDLE, 'Verify entries'),
                ]
            ],
        ];
    }

    /**
     * Registers the plugin's dashboard widgets. The personal review widget only appears for
     * users who are allowed to verify.
     *
     * @param RegisterComponentTypesEvent $event
     * @return void
     * @see Dashboard::EVENT_REGISTER_WIDGET_TYPES
     * @see registerPluginReadyEvents()
     */
    protected function onRegisterWidgets(RegisterComponentTypesEvent $event): void
    {
        $event->types[] = VerificationHealthWidget::class;

        $currentUser = Craft::$app->getUser();
        if ($currentUser->checkPermission(Permission::VerifyEntries->value)) {
            $event->types[] = ElementsToReviewWidget::class;
        }
    }

    /**
     * Adds the "Verified Elements" screen to the account pages of users who can verify.
     *
     * @param DefineEditUserScreensEvent $event
     * @return void
     * @see UsersController::EVENT_DEFINE_EDIT_SCREENS
     * @see registerPluginReadyEvents()
     */
    protected function onDefineEditScreens(DefineEditUserScreensEvent $event): void
    {
        if (! $event->editedUser->can(Permission::VerifyEntries->value)) {
            return;
        }

        $event->screens[Plugin::HANDLE] = [
            'label' => Craft::t(Plugin::HANDLE, 'Verified Elements'),
        ];
    }


    // ENTRY events
    // =============================================================================================

    /**
     * Registers every event that makes entries verifiable: behaviors, save lifecycle, index UI,
     * and edit-page UI.
     *
     * Entry condition rules and the plugin's CP URL rules are registered separately in
     * registerPluginInitEvents() because they must attach before Craft::$app->onInit() runs.
     *
     * @return void
     * @see Plugin::init()
     */
    public function registerEntryEvents(): void
    {
        if (! $this->isCpRequest && ! $this->isConsoleRequest) {
            return;
        }

        $this->registerElementBehaviors(Entry::class, EntryQuery::class);

        if (! $this->isCpRequest) {
            return;
        }

        // element CRUD operations and lifecycle
        Event::on(Entry::class, Element::EVENT_BEFORE_SAVE, $this->onBeforeSaveEntry(...));
        Event::on(Entry::class, Element::EVENT_AFTER_SAVE, $this->onAfterSaveEntry(...));

        // "element index" pages
        Event::on(Entry::class, Element::EVENT_REGISTER_SORT_OPTIONS, $this->onRegisterElementIndexSortOptions(...));
        Event::on(Entry::class, Element::EVENT_REGISTER_ACTIONS, $this->onRegisterElementIndexActions(...));
        Event::on(Entry::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, $this->onRegisterElementIndexTableAttributes(...));
        Event::on(Entry::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, $this->onDefineElementIndexAttributeHtml(...));

        // element "edit" pages
        Event::on(Entry::class, Element::EVENT_DEFINE_METADATA, $this->onDefineEntryMetadata(...));
        Event::on(Entry::class, Element::EVENT_DEFINE_SIDEBAR_HTML, $this->onDefineEntrySidebarHtml(...));
        Event::on(Entry::class, Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML, $this->onDefineEntryInlineInputHtml(...));
    }

    /**
     * Resolves the entry's default Reviewer and "Verified until" date before it saves.
     *
     * @param ModelEvent $event
     * @return void
     * @see Element::EVENT_BEFORE_SAVE
     * @see registerEntryEvents()
     */
    protected function onBeforeSaveEntry(ModelEvent $event): void
    {
        /** @var Entry|VerifiableBehavior $entry */
        $entry = $event->sender;

        // Skip for propagation, matrix entries, drafts, and revisions.
        if ($entry->propagating || $entry->sectionId === null) {
            return;
        }

        // Before an entry saves, set the Reviewer ID and "Verified until" date in
        // the entry's behavior class.
        $service = VerificationFieldsSetter::fromElement(
            $entry,
            $this->plugin->getPluginSettings()
        );

        $service->updateElementFields($entry);
    }

    /**
     * Persists the entry's verification state across its sites and notifies its Reviewer of
     * the change.
     *
     * @param ModelEvent $event
     * @return void
     * @throws Exception
     * @see Element::EVENT_AFTER_SAVE
     * @see registerEntryEvents()
     */
    protected function onAfterSaveEntry(ModelEvent $event): void
    {
        /** @var Entry|VerifiableBehavior $entry */
        $entry = $event->sender;

        // Matrix entries have no section and are never verifiable.
        if ($entry->sectionId === null) {
            return;
        }

        $settings = $this->plugin->getPluginSettings();
        $isSectionEnabledForSite = $settings->isContainerEnabledForSite(
            $entry->sectionId,
            $entry->siteId,
            Entry::class
        );
        if (! $isSectionEnabledForSite) {
            return;
        }

        // Existing drafts and revisions never sync verification state.
        if (ElementHelper::isDraftOrRevision($entry) && ! $event->isNew) {
            return;
        }

        // On editions without multi-site, ignore saves for any site but the primary -
        // this also stops propagation invocations from seeding non-primary records.
        if (! $settings->isSiteInScope($entry->siteId)) {
            return;
        }

        // The sites this entry is enabled in, confined to sites this edition may write to
        // (all sites when multi-site is on; the primary site alone otherwise).
        $supportedSiteIds = array_values(array_intersect(
            array_column(ElementHelper::supportedSitesForElement($entry), 'siteId'),
            $settings->getInScopeSiteIds()
        ));

        // The service worker that will keep the entry synced across its supported sites
        // and handle consequences from changes to it.
        $service = new VerificationStateSynchronizer(
            ElementData::fromElement($entry),
            $supportedSiteIds,
            $entry->enabled,
            $settings,
            Craft::$app->getUser()->getId()
        );

        // Since this is a new entry, and the entry is still a draft/revision, write a
        // verification record in the db for its canonical ID on the current site. No
        // further actions are needed until the entry is officially saved.
        if (ElementHelper::isDraftOrRevision($entry)) {
            $service->saveVerificationRecord();
            return;
        }

        // On a multi-site save, Craft re-fires AFTER_SAVE for each site this entry is
        // enabled in when it propagates the entry's data across its site-counterparts.
        // It's possible the record for whichever site is getting saved right now doesn't
        // yet exist. If it doesn't, create it and exit.
        if ($entry->propagating) {
            $service->ensurePropagatedRecord();
            return;
        }

        // Handle everything else for the existing entry when it's saved normally.
        $service->saveVerificationRecord();
        $service->ensureOtherSiteRecords();
        $service->notifyReviewerOnChange();
    }

    /**
     * Adds the entry's verification status to the edit page's metadata list.
     *
     * @param DefineMetadataEvent $event
     * @return void
     * @see Element::EVENT_DEFINE_METADATA
     * @see registerEntryEvents()
     */
    protected function onDefineEntryMetadata(DefineMetadataEvent $event): void
    {
        /** @var Entry|VerifiableBehavior $entry */
        $entry = $event->sender;

        // Matrix entries have no section and are never verifiable.
        if ($entry->sectionId === null) {
            return;
        }

        $settings = $this->plugin->getPluginSettings();

        // The plugin manages only in-scope sites; omit verification metadata on others.
        if (! $settings->isSiteInScope($entry->siteId)) {
            return;
        }

        // Only sections enabled for verification show a status.
        if (! $settings->isContainerEnabledForSite($entry->sectionId, $entry->siteId, Entry::class)) {
            return;
        }

        $status = $entry->getVerificationStatus();
        $statusHtml = Cp::statusIndicatorHtml(
            $status->handle(),
            ['color' => $status->color()]
        );
        $statusHtml .= Html::tag('span', $status->label());

        $event->metadata[Craft::t(Plugin::HANDLE, 'Verification')] = $statusHtml;
    }

    /**
     * Appends the plugin's verification sidebar to the entry's edit page.
     *
     * @param DefineHtmlEvent $event
     * @return void
     * @see Element::EVENT_DEFINE_SIDEBAR_HTML
     * @see registerEntryEvents()
     */
    protected function onDefineEntrySidebarHtml(DefineHtmlEvent $event): void
    {
        $currentUser = Craft::$app->getUser();
        if (! $currentUser->getIsAdmin() && ! $currentUser->checkPermission(Permission::VerifyEntries->value)) {
            return;
        }

        /** @var Entry|VerifiableBehavior $entry */
        $entry = $event->sender;
        if ($entry->sectionId === null) {
            return;
        }

        $settings = $this->plugin->getPluginSettings();

        // The plugin manages only in-scope sites; omit the sidebar on others.
        if (! $settings->isSiteInScope($entry->siteId)) {
            return;
        }

        $isSectionEnabled = $settings->isContainerEnabledForSite(
            $entry->sectionId,
            $entry->siteId,
            Entry::class
        );

        if (! $isSectionEnabled) {
            return;
        }

        $event->html .= (new CpEditSidebarRenderer($entry, $settings))->buildHtml();
    }

    /**
     * Renders the Reviewer and "Verified until" inputs for inline editing on entry indexes.
     *
     * @param DefineAttributeHtmlEvent $event
     * @return void
     * @see Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML
     * @see registerEntryEvents()
     */
    protected function onDefineEntryInlineInputHtml(DefineAttributeHtmlEvent $event): void
    {
        /** @var Entry|VerifiableBehavior $entry */
        $entry = $event->sender;

        // The plugin manages only in-scope sites; render no fields on others.
        if (! $this->plugin->getPluginSettings()->isSiteInScope($entry->siteId)) {
            return;
        }

        /** @noinspection DuplicatedCode */
        $currentUser = Craft::$app->getUser();
        $canVerifyEntries = $currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::VerifyEntries->value);

        $service = new VerificationFieldsRenderer(
            $entry,
            $canVerifyEntries,
            $this->plugin->getPluginSettings()
        );

        if ($event->attribute === 'reviewer') {
            $event->html = $service->buildReviewerFieldHtml();
        }
        elseif ($event->attribute === 'verifiedUntilDate') {
            $event->html = $service->buildVerifiedUntilDateFieldHtml();
        }
    }


    // ASSET events
    // =============================================================================================

    /**
     * Registers every event that makes assets verifiable: behaviors, save lifecycle, index UI,
     * and edit-page UI.
     *
     * Asset condition rules are registered separately in registerPluginInitEvents() because
     * they must attach before Craft::$app->onInit() runs.
     *
     * @return void
     * @see Plugin::init()
     */
    public function registerAssetEvents(): void
    {
        if (! $this->isCpRequest && ! $this->isConsoleRequest) {
            return;
        }

        $this->registerElementBehaviors(Asset::class, AssetQuery::class);

        if (! $this->isCpRequest) {
            return;
        }

        // element CRUD operations and lifecycle
        Event::on(Asset::class, Element::EVENT_BEFORE_SAVE, $this->onBeforeSaveAsset(...));
        Event::on(Asset::class, Element::EVENT_AFTER_SAVE, $this->onAfterSaveAsset(...));

        // "element index" pages
        Event::on(Asset::class, Element::EVENT_REGISTER_SORT_OPTIONS, $this->onRegisterElementIndexSortOptions(...));
        Event::on(Asset::class, Element::EVENT_REGISTER_ACTIONS, $this->onRegisterElementIndexActions(...));
        Event::on(Asset::class, Element::EVENT_REGISTER_TABLE_ATTRIBUTES, $this->onRegisterElementIndexTableAttributes(...));
        Event::on(Asset::class, Element::EVENT_DEFINE_ATTRIBUTE_HTML, $this->onDefineElementIndexAttributeHtml(...));

        // element "edit" pages
        Event::on(Asset::class, Element::EVENT_DEFINE_METADATA, $this->onDefineAssetMetadata(...));
        Event::on(Asset::class, Element::EVENT_DEFINE_SIDEBAR_HTML, $this->onDefineAssetSidebarHtml(...));
        Event::on(Asset::class, Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML, $this->onDefineAssetInlineInputHtml(...));
    }

    /**
     * Resolves the asset's default Reviewer and "Verified until" date before it saves, and
     * stashes whether its alt text changed for onAfterSaveAsset() to read.
     *
     * @param ModelEvent $event
     * @return void
     * @see Element::EVENT_BEFORE_SAVE
     * @see registerAssetEvents()
     */
    protected function onBeforeSaveAsset(ModelEvent $event): void
    {
        /** @var Asset|VerifiableBehavior $asset */
        $asset = $event->sender;

        // Skip for propagation, folders, and temporary uploads (no volume yet).
        if ($asset->propagating || $asset->isFolder || $asset->volumeId === null) {
            return;
        }

        // Before an asset saves, set the Reviewer ID and "Verified until" date in
        // the asset's behavior class.
        $service = VerificationFieldsSetter::fromElement(
            $asset,
            $this->plugin->getPluginSettings()
        );

        $service->updateElementFields($asset);

        // A new asset has no stored alt text to compare against.
        if ($event->isNew) {
            return;
        }

        // Assets never mark dirty attributes, so detect an alt-text change by
        // comparing the incoming value against the stored one, and stash the result
        // on the behavior for onAfterSaveAsset() to read. The live per-site value lives
        // in `assets_sites`; `assets.alt` is only a canonical fallback.
        $storedAlt = (new Query())
            ->select('alt')
            ->from(Table::ASSETS_SITES)
            ->where([
                'assetId' => $asset->id,
                'siteId' => $asset->siteId,
            ])
            ->scalar();

        // No row for this site yet, so there's no stored value.
        if ($storedAlt === false) {
            $storedAlt = null;
        }

        $asset->altChanged = $asset->alt !== $storedAlt;
    }

    /**
     * Persists the asset's verification state across its sites and notifies its Reviewer when
     * the file was replaced or its field content changed.
     *
     * @param ModelEvent $event
     * @return void
     * @throws Exception
     * @see Element::EVENT_AFTER_SAVE
     * @see registerAssetEvents()
     */
    protected function onAfterSaveAsset(ModelEvent $event): void
    {
        /** @var Asset|VerifiableBehavior $asset */
        $asset = $event->sender;

        // Folders and temporary uploads (no volume yet) are never verifiable.
        if ($asset->isFolder || $asset->volumeId === null) {
            return;
        }

        $settings = $this->plugin->getPluginSettings();
        $isVolumeEnabledForSite = $settings->isContainerEnabledForSite(
            $asset->volumeId,
            $asset->siteId,
            Asset::class
        );
        if (! $isVolumeEnabledForSite) {
            return;
        }

        // Note: assets have no drafts or revisions, so the entry lifecycle's
        // draft/revision handling has no asset equivalent.

        // Returns a list of sites in which this asset is enabled.
        $supportedSiteIds = array_column(
            ElementHelper::supportedSitesForElement($asset),
            'siteId'
        );

        // The service worker that will keep the asset synced across its supported sites
        // and handle consequences from changes to it.
        $service = new VerificationStateSynchronizer(
            ElementData::fromElement($asset),
            $supportedSiteIds,
            $asset->enabled,
            $settings,
            Craft::$app->getUser()->getId()
        );

        // On a multi-site save, Craft re-fires AFTER_SAVE for each site this asset is
        // enabled in when it propagates the asset's data across its site-counterparts.
        // It's possible the record for whichever site is getting saved right now doesn't
        // yet exist. If it doesn't, create it and exit.
        if ($asset->propagating) {
            $service->ensurePropagatedRecord();
            return;
        }

        $service->saveVerificationRecord();
        $service->ensureOtherSiteRecords();

        if (AssetHelper::hasNotifiableContentChange($asset, $event->isNew)) {
            $service->notifyReviewerOnChange();
        }
    }

    /**
     * Adds the asset's verification status to the edit page's metadata list.
     *
     * @param DefineMetadataEvent $event
     * @return void
     * @see Element::EVENT_DEFINE_METADATA
     * @see registerAssetEvents()
     */
    protected function onDefineAssetMetadata(DefineMetadataEvent $event): void
    {
        /** @var Asset|VerifiableBehavior $asset */
        $asset = $event->sender;

        // Folders and temporary uploads (no volume yet) are never verifiable.
        if ($asset->isFolder || $asset->volumeId === null) {
            return;
        }

        $settings = $this->plugin->getPluginSettings();

        // The plugin manages only in-scope sites; omit verification metadata on others.
        if (! $settings->isSiteInScope($asset->siteId)) {
            return;
        }

        // Only volumes enabled for verification show a status.
        if (! $settings->isContainerEnabledForSite($asset->volumeId, $asset->siteId, Asset::class)) {
            return;
        }

        $status = $asset->getVerificationStatus();
        $statusHtml = Cp::statusIndicatorHtml(
            $status->handle(),
            ['color' => $status->color()]
        );
        $statusHtml .= Html::tag('span', $status->label());

        $event->metadata[Craft::t(Plugin::HANDLE, 'Verification')] = $statusHtml;
    }

    /**
     * Appends the plugin's verification sidebar to the asset's edit page.
     *
     * @param DefineHtmlEvent $event
     * @return void
     * @see Element::EVENT_DEFINE_SIDEBAR_HTML
     * @see registerAssetEvents()
     */
    protected function onDefineAssetSidebarHtml(DefineHtmlEvent $event): void
    {
        $currentUser = Craft::$app->getUser();
        if (! $currentUser->getIsAdmin() && ! $currentUser->checkPermission(Permission::VerifyEntries->value)) {
            return;
        }

        /** @var Asset|VerifiableBehavior $asset */
        $asset = $event->sender;

        // Folders and temporary uploads (no volume yet) are never verifiable.
        if ($asset->isFolder || $asset->volumeId === null) {
            return;
        }

        $settings = $this->plugin->getPluginSettings();

        // The plugin manages only in-scope sites; omit the sidebar on others.
        if (! $settings->isSiteInScope($asset->siteId)) {
            return;
        }

        $isVolumeEnabled = $settings->isContainerEnabledForSite(
            $asset->volumeId,
            $asset->siteId,
            Asset::class
        );

        if (! $isVolumeEnabled) {
            return;
        }

        $event->html .= (new CpEditSidebarRenderer($asset, $settings))->buildHtml();
    }

    /**
     * Renders the Reviewer and "Verified until" inputs for inline editing on asset indexes.
     *
     * @param DefineAttributeHtmlEvent $event
     * @return void
     * @see Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML
     * @see registerAssetEvents()
     */
    protected function onDefineAssetInlineInputHtml(DefineAttributeHtmlEvent $event): void
    {
        /** @var Asset|VerifiableBehavior $asset */
        $asset = $event->sender;

        // Folders and temporary uploads (no volume yet) are never verifiable.
        if ($asset->isFolder || $asset->volumeId === null) {
            return;
        }

        // The plugin manages only in-scope sites; render no fields on others.
        if (! $this->plugin->getPluginSettings()->isSiteInScope($asset->siteId)) {
            return;
        }

        /** @noinspection DuplicatedCode */
        $currentUser = Craft::$app->getUser();
        $canVerifyEntries = $currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::VerifyEntries->value);

        $service = new VerificationFieldsRenderer(
            $asset,
            $canVerifyEntries,
            $this->plugin->getPluginSettings()
        );

        if ($event->attribute === 'reviewer') {
            $event->html = $service->buildReviewerFieldHtml();
        }
        elseif ($event->attribute === 'verifiedUntilDate') {
            $event->html = $service->buildVerifiedUntilDateFieldHtml();
        }
    }


    // (SHARED) ELEMENT events
    // =============================================================================================

    /**
     * Events that define what an element is at the Model level.
     *
     * @param string $elementClass
     * @param string $queryClass
     * @return void
     * @see Model::EVENT_DEFINE_RULES
     * @see Model::EVENT_DEFINE_BEHAVIORS
     * @see Query::EVENT_DEFINE_BEHAVIORS
     * @see registerAssetEvents()
     * @see registerEntryEvents()
     */
    protected function registerElementBehaviors(string $elementClass, string $queryClass): void
    {
        Event::on(
            $elementClass,
            Model::EVENT_DEFINE_RULES,
            static function (DefineRulesEvent $event) {
                $event->rules[] = [['reviewerId'], 'number', 'integerOnly' => true];
                $event->rules[] = [['verifiedUntilDate'], DateTimeValidator::class];
            }
        );

        Event::on(
            $elementClass,
            Model::EVENT_DEFINE_BEHAVIORS,
            static function (DefineBehaviorsEvent $event) {
                $event->behaviors[VerifiableBehavior::NAME] = VerifiableBehavior::class;
            }
        );

        Event::on(
            $queryClass,
            Query::EVENT_DEFINE_BEHAVIORS,
            static function (DefineBehaviorsEvent $event) {
                $event->behaviors[VerifiableQueryBehavior::NAME] = VerifiableQueryBehavior::class;
            }
        );
    }

    /**
     * Adds the "Verified until" sort option to element indexes.
     *
     * @param RegisterElementSortOptionsEvent $event
     * @return void
     * @see Element::EVENT_REGISTER_SORT_OPTIONS
     * @see registerAssetEvents()
     * @see registerEntryEvents()
     */
    protected function onRegisterElementIndexSortOptions(RegisterElementSortOptionsEvent $event): void
    {
        $event->sortOptions[] = [
            'label' => Craft::t(Plugin::HANDLE, 'Verified until'),
            'orderBy' => 'verifiedUntilDate',
            'defaultDir' => 'desc',
        ];
    }

    /**
     * Adds the "Verify" and "Assign Reviewer" bulk actions to element indexes for users who
     * are allowed to verify.
     *
     * @param RegisterElementActionsEvent $event
     * @return void
     * @see Element::EVENT_REGISTER_ACTIONS
     * @see registerAssetEvents()
     * @see registerEntryEvents()
     */
    protected function onRegisterElementIndexActions(RegisterElementActionsEvent $event): void
    {
        $currentUser = Craft::$app->getUser();

        if ($currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::VerifyEntries->value)) {
            $event->actions[] = VerifyElement::class;
            $event->actions[] = AssignReviewer::class;
        }
    }

    /**
     * Adds the plugin's columns ("Verified until", "Verification", "Reviewer") to element
     * indexes.
     *
     * @param RegisterElementTableAttributesEvent $event
     * @return void
     * @see Element::EVENT_REGISTER_TABLE_ATTRIBUTES
     * @see registerAssetEvents()
     * @see registerEntryEvents()
     */
    protected function onRegisterElementIndexTableAttributes(RegisterElementTableAttributesEvent $event): void
    {
        $event->tableAttributes['verifiedUntilDate'] = [
            'label' => Craft::t(Plugin::HANDLE, 'Verified until')
        ];

        $event->tableAttributes['isVerified'] = [
            'label' => Craft::t(Plugin::HANDLE, 'Verification'),
        ];

        $event->tableAttributes['reviewer'] = [
            'label' => Craft::t(Plugin::HANDLE, 'Reviewer'),
        ];
    }

    /**
     * Renders the values of the plugin's columns on element indexes.
     *
     * @param DefineAttributeHtmlEvent $event
     * @return void
     * @see Element::EVENT_DEFINE_ATTRIBUTE_HTML
     * @see registerAssetEvents()
     * @see registerEntryEvents()
     */
    protected function onDefineElementIndexAttributeHtml(DefineAttributeHtmlEvent $event): void
    {
        /** @var Element|VerifiableBehavior $element */
        $element = $event->sender;

        if ($event->attribute === 'isVerified') {
            $status = $element->getVerificationStatus();
            $event->html = Cp::statusLabelHtml([
                'color' => $status->color(),
                'label' => $status->label(),
            ]);
            return;
        }

        if ($event->attribute === 'verifiedUntilDate') {
            $event->html = DateHelper::readableVerificationDate($element->getVerifiedUntilDate());
            return;
        }

        if ($event->attribute === 'reviewer') {
            if ($reviewer = $element->getReviewer()) {
                $event->html = Cp::elementChipHtml($reviewer);
                return;
            }

            $event->html = Html::tag(
                'span',
                Craft::t(Plugin::HANDLE, 'Unassigned'),
                [
                    'class' => 'light',
                    'style' => ['font-style' => 'italic'],
                ]
            );
        }
    }
}
