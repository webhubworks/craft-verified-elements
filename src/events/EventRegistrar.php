<?php

namespace webhubworks\verifiedentries\events;

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
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\validators\DateTimeValidator;
use craft\web\UrlManager;
use DateTime;
use Twig\TwigFilter;
use webhubworks\verifiedentries\helpers\Log;
use webhubworks\verifiedentries\models\SystemRecipient;
use webhubworks\verifiedentries\models\UserRecipient;
use webhubworks\verifiedentries\services\EntrySidebarRenderer;
use webhubworks\verifiedentries\services\ExpiredVerificationNotifier;
use webhubworks\verifiedentries\services\VerificationFieldsRenderer;
use webhubworks\verifiedentries\services\VerificationFieldsSetter;
use webhubworks\verifiedentries\services\VerificationStateSynchronizer;
use webhubworks\verifiedentries\VerifiedEntries;
use webhubworks\verifiedentries\behaviors\VerifiableBehavior;
use webhubworks\verifiedentries\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedentries\elements\VerifiedEntry;
use webhubworks\verifiedentries\elements\actions\AssignReviewer;
use webhubworks\verifiedentries\elements\actions\VerifyEntry;
use webhubworks\verifiedentries\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedUntilDateConditionRule;
use webhubworks\verifiedentries\enums\Permission;
use webhubworks\verifiedentries\widgets\EntriesToReview;
use webhubworks\verifiedentries\widgets\VerificationHealth;

/**
 * Helper class for registering the plugin's event handlers.
 * @see VerifiedEntries::init()
 */
readonly class EventRegistrar
{
    public function __construct(
        private VerifiedEntries $plugin,
        private bool            $isCpRequest,
        private bool            $isConsoleRequest,
    ) {}


    // PRE-INIT EVENTS
    // =============================================================================================

    /**
     * Events that must run before Craft::$app->onInit() runs.
     *
     * @param VerifiedEntries $plugin
     * @param bool $isCpRequest
     * @param bool $isConsoleRequest
     * @return void
     * @see BaseCondition::EVENT_REGISTER_CONDITION_RULES
     * @see Gc::EVENT_RUN
     * @see UrlManager::EVENT_REGISTER_CP_URL_RULES
     * @see VerifiedEntries::init()
     */
    public static function registerEarlyEvents(VerifiedEntries $plugin, bool $isCpRequest, bool $isConsoleRequest): void
    {
        if (! $isCpRequest && ! $isConsoleRequest) {
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

                $service = new ExpiredVerificationNotifier();

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

        if (! $isCpRequest) {
            return;
        }

        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            static function (RegisterUrlRulesEvent $event) {
                $currentUser = Craft::$app->getUser();

                $event->rules[VerifiedEntries::HANDLE] = VerifiedEntries::HANDLE . '/entries/index';

                if ($currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::ManageVerificationSettings->value)) {
                    $event->rules[VerifiedEntries::HANDLE . '/settings'] = VerifiedEntries::HANDLE . '/settings/index';
                    $event->rules[VerifiedEntries::HANDLE . '/settings/grouped'] = VerifiedEntries::HANDLE . '/settings/grouped';
                }

                // User edit screen
                $event->rules['myaccount/' . VerifiedEntries::HANDLE] = VerifiedEntries::HANDLE . '/reviewers/index';
                $event->rules['users/<userId:\d+>/' . VerifiedEntries::HANDLE] = VerifiedEntries::HANDLE . '/reviewers/index';
            }
        );
    }


    // ON-INIT EVENTS
    // =============================================================================================

    /**
     * Events that run during Craft::$app->onInit(). This is the parent method that triggers all
     * private methods below.
     *
     * @return void
     * @see VerifiedEntries::init()
     */
    public function registerOnInitEvents(): void
    {
        // the order that these methods run doesn't matter.
        $this->registerEntryBehaviors();
        $this->registerEntryEditUi();
        $this->registerEntryIndexUi();
        $this->registerEntryLifecycle();
        $this->registerCraftComponents();
        $this->extendTwig();
    }

    /**
     * Extend Twig for the plugin's needs.
     *
     * @return void
     */
    public function extendTwig(): void
    {
        // define Twig constants
        Craft::$app->getView()->getTwig()->addGlobal('pluginHandle', VerifiedEntries::HANDLE);

        // define custom Twig functions
        Craft::$app->getView()->getTwig()->addFilter(
            new TwigFilter(
                'readableVerificationDate',
                fn(?DateTime $date): string => VerifiedEntries::getInstance()
                    ->getVerification()
                    ->makeVerificationDateReadable($date)
            )
        );
    }

    /**
     * Events that run during CRUD operations on entries.
     *
     * @return void
     * @see Element::EVENT_BEFORE_SAVE
     * @see Element::EVENT_AFTER_SAVE
     */
    private function registerEntryLifecycle(): void
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
                        $entry->sectionId === null ||
                        ElementHelper::isDraftOrRevision($entry)
                    ) {
                        return;
                    }

                    // Before an entry saves, set the Reviewer ID and "Verified until" date in
                    // the entry's behavior class.
                    $service = VerificationFieldsSetter::fromEntry(
                        $entry,
                        $this->plugin->getSectionSettings()
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

                if (ElementHelper::isDraftOrRevision($entry)) {
                    return;
                }

                $service = new VerificationStateSynchronizer(
                    $entry,
                    $this->plugin->getSectionSettings(),
                    Craft::$app->getUser()->getId()
                );

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
    private function registerEntryBehaviors(): void
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
    private function registerEntryEditUi(): void
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

                $event->metadata[Craft::t(VerifiedEntries::HANDLE, 'Verification')] = $statusHtml;
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
                /** @var Entry|VerifiableBehavior $entry */
                $entry = $event->sender;

                $currentUser = Craft::$app->getUser();
                if (! $currentUser->getIsAdmin() && ! $currentUser->checkPermission(Permission::VerifyEntries->value)) {
                    return;
                }

                $settings = $this->plugin->getSectionSettings();
                $isSectionEnabled = $settings->isSectionEnabledForSite(
                    $entry->sectionId,
                    $entry->siteId
                );
                if (!$isSectionEnabled) {
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
                    $this->plugin->getSectionSettings()
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
    private function registerEntryIndexUi(): void
    {
        if (! $this->isCpRequest) {
            return;
        }

        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_SORT_OPTIONS,
            static function (RegisterElementSortOptionsEvent $event) {
                $event->sortOptions[] = [
                    'label' => Craft::t(VerifiedEntries::HANDLE, 'Verified until'),
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
                    'label' => Craft::t(VerifiedEntries::HANDLE, 'Verified until')
                ];

                $event->tableAttributes['isVerified'] = [
                    'label' => Craft::t(VerifiedEntries::HANDLE, 'Verification'),
                ];

                $event->tableAttributes['reviewer'] = [
                    'label' => Craft::t(VerifiedEntries::HANDLE, 'Reviewer'),
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
                        $event->html = VerifiedEntries::getInstance()
                            ->getVerification()
                            ->makeVerificationDateReadable($entry->getVerifiedUntilDate());
                        break;

                    case "reviewer":
                        $reviewer = $entry->getReviewer();
                        if ($reviewer) {
                            $event->html = Cp::elementChipHtml($reviewer);
                        }
                        else {
                            $event->html = Html::tag(
                                'span',
                                Craft::t(VerifiedEntries::HANDLE, 'Unassigned'),
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

    /**
     * Events that register plugin features into Craft's various systems.
     *
     * @return void
     * @see Elements::EVENT_REGISTER_ELEMENT_TYPES
     * @see UserPermissions::EVENT_REGISTER_PERMISSIONS
     * @see Dashboard::EVENT_REGISTER_WIDGET_TYPES
     * @see UsersController::EVENT_DEFINE_EDIT_SCREENS
     */
    private function registerCraftComponents(): void
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
                    'heading' => Craft::t(VerifiedEntries::HANDLE, 'Verified Entries'),
                    'permissions' => [
                        Permission::ManageVerificationSettings->value => [
                            'label' => Craft::t(VerifiedEntries::HANDLE, 'Manage Verification Settings'),
                        ],
                        Permission::VerifyEntries->value => [
                            'label' => Craft::t(VerifiedEntries::HANDLE, 'Verify entries'),
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

                $event->screens[VerifiedEntries::HANDLE] = [
                    'label' => Craft::t(VerifiedEntries::HANDLE, 'Verified Entries'),
                ];
            }
        );
    }
}
