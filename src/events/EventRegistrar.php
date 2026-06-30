<?php

namespace webhubworks\verifiedelements\events;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\base\Model;
use craft\base\conditions\BaseCondition;
use craft\controllers\UsersController;
use craft\db\Query;
use craft\elements\Entry;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\db\EntryQuery;
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
use webhubworks\verifiedelements\helpers\DateHelper;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\models\SystemRecipient;
use webhubworks\verifiedelements\models\UserRecipient;
use webhubworks\verifiedelements\services\EntrySidebarRenderer;
use webhubworks\verifiedelements\services\ExpiredVerificationNotifier;
use webhubworks\verifiedelements\services\VerificationFieldsRenderer;
use webhubworks\verifiedelements\services\VerificationFieldsSetter;
use webhubworks\verifiedelements\services\VerificationStateSynchronizer;
use webhubworks\verifiedelements\Plugin;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedelements\elements\VerifiedEntry;
use webhubworks\verifiedelements\elements\actions\AssignReviewer;
use webhubworks\verifiedelements\elements\actions\VerifyEntry;
use webhubworks\verifiedelements\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedelements\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedelements\elements\conditions\VerifiedUntilDateConditionRule;
use webhubworks\verifiedelements\enums\Permission;
use webhubworks\verifiedelements\widgets\EntriesToReview;
use webhubworks\verifiedelements\widgets\VerificationHealth;

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


    // GLOBAL events
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
    public function registerEarlyEvents(): void
    {
        if (! $this->isCpRequest && ! $this->isConsoleRequest) {
            return;
        }

        Event::on(
            EntryCondition::class,
            BaseCondition::EVENT_REGISTER_CONDITION_RULES,
            static function (RegisterConditionRulesEvent $event) {
                $event->conditionRules[] = VerifiedConditionRule::class;
                $event->conditionRules[] = VerifiedUntilDateConditionRule::class;
                $event->conditionRules[] = ReviewerConditionRule::class;
            }
        );

        Event::on(
            Gc::class,
            Gc::EVENT_RUN,
            static function () {

                $service = new ExpiredVerificationNotifier(Dispatcher::TARGET_WEB);

                foreach ($service->getExpiredEntriesByReviewer() as $reviewerId => $expiredEntries) {

                    // 1. Find the Reviewer
                    if (! $reviewer = $service->getReviewer($reviewerId)) {
                        Log::warning(
                            "Reviewer $reviewerId not found or inactive — skipping expired notification.",
                            __METHOD__
                        );
                        $service->reassignEntriesToUnassigned($reviewerId);
                        continue;
                    }

                    // 2. Notify the Reviewer
                    $isSent = $service->notifyRecipient(
                        new UserRecipient($reviewer),
                        $expiredEntries
                    );

                    if (! $isSent) {
                        Log::warning(
                            "Failed to send expired notification to User $reviewer->id.",
                            __METHOD__
                        );
                    }
                }

                if (! $service->hasUnassignedExpiredEntries()) {
                    return;
                }

                // 1. Notify the system admin
                $recipient = new SystemRecipient();
                $isSent = $service->notifyRecipient(
                    $recipient,
                    $service->getUnassignedExpiredEntries()
                );

                if (! $isSent) {
                    Log::warning(
                        "Failed to send expired notification to " . $recipient->getEmail() . '.',
                        __METHOD__
                    );
                }
            }
        );

        if (! $this->isCpRequest) {
            return;
        }

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function (RegisterUrlRulesEvent $event) {
                $currentUser = Craft::$app->getUser();

                $event->rules[Plugin::HANDLE] = Plugin::HANDLE . '/entries/index';

                if ($currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::ManageVerificationSettings->value)) {
                    $event->rules[Plugin::HANDLE . '/settings'] = Plugin::HANDLE . '/settings/index';
                    $event->rules[Plugin::HANDLE . '/settings/grouped'] = Plugin::HANDLE . '/settings/grouped';
                }

                // User edit screen
                $event->rules['myaccount/' . Plugin::HANDLE] = Plugin::HANDLE . '/reviewers/index';
                $event->rules['users/<userId:\d+>/' . Plugin::HANDLE] = Plugin::HANDLE . '/reviewers/index';
            }
        );
    }

    /**
     * Extend Twig for the plugin's needs.
     *
     * @return void
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
     * Events that register plugin features into Craft's various systems.
     *
     * @return void
     * @see Elements::EVENT_REGISTER_ELEMENT_TYPES
     * @see UserPermissions::EVENT_REGISTER_PERMISSIONS
     * @see Dashboard::EVENT_REGISTER_WIDGET_TYPES
     * @see UsersController::EVENT_DEFINE_EDIT_SCREENS
     */
    public function registerCraftComponents(): void
    {
        if ($this->isCpRequest || $this->isConsoleRequest) {
            Event::on(
                Elements::class,
                Elements::EVENT_REGISTER_ELEMENT_TYPES,
                static function (RegisterComponentTypesEvent $event) {
                    $event->types[] = VerifiedEntry::class;
                }
            );
        }

        if (! $this->isCpRequest) {
            return;
        }

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            static function (RegisterUserPermissionsEvent $event) {
                $event->permissions[] = [
                    'heading' => Craft::t(Plugin::HANDLE, 'Verified Entries'),
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
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            static function (RegisterComponentTypesEvent $event) {
                $currentUser = Craft::$app->getUser();

                $event->types[] = VerificationHealth::class;

                if ($currentUser->checkPermission(Permission::VerifyEntries->value)) {
                    $event->types[] = EntriesToReview::class;
                }
            }
        );

        Event::on(
            UsersController::class,
            UsersController::EVENT_DEFINE_EDIT_SCREENS,
            static function (DefineEditUserScreensEvent $event) {
                if (! $event->editedUser->can(Permission::VerifyEntries->value)) {
                    return;
                }

                $event->screens[Plugin::HANDLE] = [
                    'label' => Craft::t(Plugin::HANDLE, 'Verified Entries'),
                ];
            }
        );
    }


    // ENTRY events
    // =============================================================================================

    /**
     * Events that run during CRUD operations on entries.
     *
     * @return void
     * @see Element::EVENT_BEFORE_SAVE
     * @see Element::EVENT_AFTER_SAVE
     */
    public function registerEntryLifecycle(): void
    {
        if ($this->isCpRequest || $this->isConsoleRequest) {
            Event::on(
                Entry::class,
                Element::EVENT_BEFORE_SAVE,
                function (ModelEvent $event) {
                    /** @var Entry|VerifiableBehavior $entry */
                    $entry = $event->sender;

                    // Skip for propagation, matrix entries, drafts, and revisions.
                    if (
                        $entry->propagating ||
                        $entry->sectionId === null
                    ) {
                        return;
                    }

                    // Before an entry saves, set the Reviewer ID and "Verified until" date in
                    // the entry's behavior class.
                    $service = VerificationFieldsSetter::fromEntry(
                        $entry,
                        $this->plugin->getPluginSettings()
                    );

                    $service->updateEntryFields($entry);
                }
            );
        }

        if (! $this->isCpRequest) {
            return;
        }

        Event::on(
            Entry::class,
            Element::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Entry|VerifiableBehavior $entry */
                $entry = $event->sender;

                $service = new VerificationStateSynchronizer(
                    $entry,
                    $this->plugin->getPluginSettings(),
                    Craft::$app->getUser()->getId()
                );

                if (ElementHelper::isDraftOrRevision($entry)) {
                    if ($event->isNew && $service->isSectionEnabled()) {
                        $service->saveVerificationRecord();
                    }
                    return;
                }

                if (! $service->isSectionEnabled()) {
                    return;
                }

                if ($entry->propagating) {
                    $service->ensurePropagatedRecord();
                    return;
                }

                $service->saveVerificationRecord();
                $service->ensureOtherSiteRecords();
                $service->notifyReviewerOnChange();
            }
        );
    }

    /**
     * Events that define what an entry is at the Model level.
     *
     * @return void
     * @see Model::EVENT_DEFINE_RULES
     * @see Model::EVENT_DEFINE_BEHAVIORS
     * @see Query::EVENT_DEFINE_BEHAVIORS
     */
    public function registerEntryBehaviors(): void
    {
        if (! $this->isCpRequest && ! $this->isConsoleRequest) {
            return;
        }

        Event::on(
            Entry::class,
            Model::EVENT_DEFINE_RULES,
            static function (DefineRulesEvent $event) {
                $event->rules[] = [['reviewerId'], 'number', 'integerOnly' => true];
                $event->rules[] = [['verifiedUntilDate'], DateTimeValidator::class];
            }
        );

        Event::on(
            Entry::class,
            Model::EVENT_DEFINE_BEHAVIORS,
            static function (DefineBehaviorsEvent $event) {
                $event->behaviors[VerifiableBehavior::NAME] = VerifiableBehavior::class;
            }
        );

        Event::on(
            EntryQuery::class,
            Query::EVENT_DEFINE_BEHAVIORS,
            static function (DefineBehaviorsEvent $event) {
                $event->behaviors[VerifiableQueryBehavior::NAME] = VerifiableQueryBehavior::class;
            }
        );
    }

    /**
     * Events that affect an entry's "Edit" page in the CP.
     *
     * @return void
     * @see Element::EVENT_DEFINE_METADATA
     * @see Element::EVENT_DEFINE_SIDEBAR_HTML
     * @see Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML
     */
    public function registerEntryEditUi(): void
    {
        if (! $this->isCpRequest) {
            return;
        }

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_METADATA,
            static function (DefineMetadataEvent $event) {
                /** @var Entry|VerifiableBehavior $entry */
                $entry = $event->sender;

                $status = $entry->getVerificationStatus();
                $statusHtml = Cp::statusIndicatorHtml(
                    $status->handle(),
                    ['color' => $status->color()]
                );
                $statusHtml .= Html::tag('span', $status->label());

                $event->metadata[Craft::t(Plugin::HANDLE, 'Verification')] = $statusHtml;
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
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
                $isSectionEnabled = $settings->isSectionEnabledForSite(
                    $entry->sectionId,
                    $entry->siteId
                );
                if (! $isSectionEnabled) {
                    return;
                }

                $event->html .= (new EntrySidebarRenderer($entry, $settings))->buildHtml();
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML,
            function (DefineAttributeHtmlEvent $event) {
                /** @var Entry|VerifiableBehavior $entry */
                $entry = $event->sender;
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
        );
    }

    /**
     * Events that affect how entries appear and behave in the CP's element index.
     *
     * @return void
     * @see Element::EVENT_REGISTER_SORT_OPTIONS
     * @see Element::EVENT_REGISTER_ACTIONS
     * @see Element::EVENT_REGISTER_TABLE_ATTRIBUTES
     * @see Element::EVENT_DEFINE_ATTRIBUTE_HTML
     */
    public function registerEntryIndexUi(): void
    {
        if (! $this->isCpRequest) {
            return;
        }

        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_SORT_OPTIONS,
            static function (RegisterElementSortOptionsEvent $event) {
                $event->sortOptions[] = [
                    'label' => Craft::t(Plugin::HANDLE, 'Verified until'),
                    'orderBy' => 'verifiedUntilDate',
                    'defaultDir' => 'desc',
                ];
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_ACTIONS,
            static function (RegisterElementActionsEvent $event) {
                $currentUser = Craft::$app->getUser();

                if ($currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::VerifyEntries->value)) {
                    $event->actions[] = VerifyEntry::class;
                    $event->actions[] = AssignReviewer::class;
                }
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_TABLE_ATTRIBUTES,
            static function (RegisterElementTableAttributesEvent $event) {
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
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML,
            static function (DefineAttributeHtmlEvent $event) {
                /** @var Entry|VerifiableBehavior $entry */
                $entry = $event->sender;

                switch ($event->attribute) {
                    case "isVerified":
                        $status = $entry->getVerificationStatus();
                        $event->html = Cp::statusLabelHtml([
                            'color' => $status->color(),
                            'label' => $status->label(),
                        ]);
                        break;

                    case "verifiedUntilDate":
                        $event->html = DateHelper::readableVerificationDate($entry->getVerifiedUntilDate());
                        break;

                    case "reviewer":
                        $reviewer = $entry->getReviewer();
                        if ($reviewer) {
                            $event->html = Cp::elementChipHtml($reviewer);
                        }
                        else {
                            $event->html = Html::tag(
                                'span',
                                Craft::t(Plugin::HANDLE, 'Unassigned'),
                                [
                                    'class' => 'light',
                                    'style' => ['font-style' => 'italic'],
                                ]
                            );
                        }
                        break;
                }
            }
        );
    }


    // ASSET events
    // =============================================================================================
}
