<?php

namespace webhubworks\verifiedentries\events;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\base\Model;
use craft\base\conditions\BaseCondition;
use craft\behaviors\RevisionBehavior;
use craft\controllers\UsersController;
use craft\db\Query;
use craft\elements\Entry;
use craft\elements\User;
use craft\elements\conditions\entries\EntryCondition;
use craft\elements\db\EntryQuery;
use craft\enums\Color;
use craft\events\DefineAttributeHtmlEvent;
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
use craft\helpers\DateTimeHelper;
use craft\helpers\ElementHelper;
use craft\helpers\Html;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\validators\DateTimeValidator;
use craft\web\UrlManager;
use DateInterval;
use webhubworks\verifiedentries\VerifiedEntries;
use webhubworks\verifiedentries\behaviors\EntryBehavior;
use webhubworks\verifiedentries\behaviors\EntryQueryBehavior;
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
    )
    {
    }


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
            static function () use ($plugin) {
                $plugin->getVerification()->checkExpiredVerifications();
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
                    $event->rules[VerifiedEntries::HANDLE . '/settings'] = VerifiedEntries::HANDLE . '/section-settings/index';
                    $event->rules[VerifiedEntries::HANDLE . '/settings/grouped'] = VerifiedEntries::HANDLE . '/section-settings/grouped';
                }

                // User edit screen
                $event->rules['myaccount/' . VerifiedEntries::HANDLE] = VerifiedEntries::HANDLE . '/users/index';
                $event->rules['users/<userId:\d+>/' . VerifiedEntries::HANDLE] = VerifiedEntries::HANDLE . '/users/index';
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
            static function ($event) {
                $event->behaviors[EntryBehavior::NAME] = EntryBehavior::class;
            }
        );

        Event::on(
            EntryQuery::class,
            Query::EVENT_DEFINE_BEHAVIORS,
            static function ($event) {
                /** @var EntryQuery $query */

                $event->behaviors[] = EntryQueryBehavior::class;
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
                /** @var Entry|EntryBehavior $entry */
                $entry = $event->sender;

                if (! $entry->getHasVerifiedUntilDate()) {
                    $status = Cp::statusIndicatorHtml('unverified', [
                            'color' => Color::Gray,
                        ]) . Html::tag('span', Craft::t(VerifiedEntries::HANDLE, 'Unverified'));
                }
                elseif ($entry->getIsVerified()) {
                    $status = Cp::statusIndicatorHtml('live', [
                            'color' => Color::Teal,
                        ]) . Html::tag('span', Craft::t(VerifiedEntries::HANDLE, 'Verified'));
                }
                else {
                    $status = Cp::statusIndicatorHtml('expired', [
                            'color' => Color::Red,
                        ]) . Html::tag('span', Craft::t(VerifiedEntries::HANDLE, 'Expired'));
                }

                $event->metadata[Craft::t(VerifiedEntries::HANDLE, 'Verification')] = $status;
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
                /** @var Entry|EntryBehavior $entry */
                $entry = $event->sender;
                $currentUser = Craft::$app->getUser();

                if (
                    ! $entry->getIsSectionEnabledForVerification() ||
                    (! $currentUser->getIsAdmin() && ! $currentUser->checkPermission(Permission::VerifyEntries->value))
                ) {
                    return;
                }

                if (! $entry->getIsVerified()) {
                    $event->html .=
                        Html::beginTag('div', ['class' => ['meta', 'warning']]) .
                        Html::tag('p', Craft::t(VerifiedEntries::HANDLE, 'Entry has expired and is due to be verified.')) .
                        Html::endTag('div');
                }

                $verification = $this->plugin->getVerification();

                $event->html .= Craft::$app->getView()->renderTemplate(
                    VerifiedEntries::HANDLE . '/_sidebar.twig',
                    [
                        'addOptionFn' => $verification->getAddOptionFn(),
                        'verifiedUntilDate' => $entry->getVerifiedUntilDate(),
                        'isVerified' => $entry->getIsVerified(),
                        'reviewer' => $entry->getReviewer(),
                        'options' => $verification->getDateOptionsForEntry(
                            $entry->getVerifiedUntilDate(),
                            $entry->sectionId,
                            $entry->siteId
                        ),
                    ]
                );
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML,
            function (DefineAttributeHtmlEvent $event) {
                /** @var Entry|EntryBehavior $entry */
                $entry = $event->sender;
                $currentUser = Craft::$app->getUser();
                $canVerifyEntries = $currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::VerifyEntries->value);

                if ($event->attribute === 'reviewer') {
                    $reviewer = $entry->getReviewer();
                    $event->html = Cp::elementSelectHtml([
                        'id' => 'reviewerId',
                        'name' => 'reviewerId',
                        'label' => Craft::t(VerifiedEntries::HANDLE, 'Reviewer'),
                        'single' => true,
                        'elementType' => User::class,
                        'elements' => $reviewer ? [$reviewer] : null,
                        'criteria' => [
                            'status' => 'active',
                            'can' => Permission::VerifyEntries->value,
                        ],
                        'disabled' => ! $canVerifyEntries,
                    ]);
                    return;
                }

                if ($event->attribute !== 'verifiedUntilDate') {
                    return;
                }

                $verification = $this->plugin->getVerification();
                $event->html = Cp::selectizeFieldHtml([
                    'id' => 'verifiedUntilDate',
                    'name' => 'verifiedUntilDate',
                    'options' => $verification->getDateOptionsForEntry(
                        $entry->getVerifiedUntilDate(),
                        $entry->sectionId,
                        $entry->siteId
                    ),
                    'selectizeOptions' => [
                        'allowEmptyOption' => false,
                        'autocomplete' => false,
                    ],
                    'value' => $entry->getVerifiedUntilDate() ? $entry->getVerifiedUntilDate()->format('Y-m-d') : false,
                    'addOptionLabel' => 'specificDate',
                    'addOptionFn' => $verification->getAddOptionFn(),
                    'disabled' => ! $canVerifyEntries,
                ]);
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
                /** @var Entry|EntryBehavior $entry */
                $entry = $event->sender;

                switch ($event->attribute) {
                    case "isVerified":
                        if ($entry->getIsVerified()) {
                            $event->html = Cp::statusLabelHtml([
                                'color' => Color::Teal,
                                'label' => Craft::t(VerifiedEntries::HANDLE, 'Verified')
                            ]);
                        }
                        else {
                            $event->html = Cp::statusLabelHtml([
                                'color' => Color::Red,
                                'label' => Craft::t(VerifiedEntries::HANDLE, 'Expired'),
                            ]);
                        }
                        break;
                    case "verifiedUntilDate":
                        if ($entry->getVerifiedUntilDate() === null) {
                            $event->html = Craft::t(VerifiedEntries::HANDLE, 'Indefinitely');
                        }
                        // TODO address this: it was old code that came with the plugin
//                        else {
//                            $difference = date_diff(DateTimeHelper::now(), $entry->getVerifiedUntilDate());
//
//                            $event->html = DateTimeHelper::humanDuration($difference, false);
//                        }
                        break;
                    case "reviewer":
                        $reviewer = $entry->getReviewer();
                        if ($reviewer) {
                            $event->html = Cp::elementChipHtml($reviewer);
                        }
                        break;
                }
            }
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
            /**
             *
             */
            Event::on(
                Entry::class,
                Element::EVENT_BEFORE_SAVE,
                function (ModelEvent $event) {
                    if (! $event->isNew) {
                        return;
                    }

                    /** @var Entry|EntryBehavior $entry */
                    $entry = $event->sender;

                    if ($entry->sectionId === null) {
                        return;
                    }

                    $defaults = $this->plugin
                        ->getSectionSettings()
                        ->getDefaultSettingsForSection(
                            $entry->sectionId,
                            $entry->siteId
                        );

                    [$reviewerId, $defaultPeriod] = $defaults ?? [null, null];

                    if ($entry->getReviewerId() === null && $reviewerId) {
                        $entry->setReviewerId($reviewerId);
                    }

                    if ($entry->getVerifiedUntilDate() === null && $defaultPeriod) {
                        $dateInterval = new DateInterval($defaultPeriod);
                        $verifiedUntilDate = DateTimeHelper::now()->add($dateInterval);
                        $entry->setVerifiedUntilDate($verifiedUntilDate);
                    }
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
                /** @var Entry|EntryBehavior $entry */
                $entry = $event->sender;

                if (ElementHelper::isDraftOrRevision($entry)) {
                    return;
                }

                if (! $entry->getBehavior(EntryBehavior::NAME)) {
                    $entry->attachBehavior(EntryBehavior::NAME, EntryBehavior::class);
                }

                // Don't run the below logic for entries not affected by this plugin.
                if (! $entry->getIsSectionEnabledForVerification()) {
                    return;
                }

                // Should this entry's verification fields ba applied to other site-versions of this entry?
                if ($entry->propagating) {
                    $this->plugin->getVerification()->handlePropagationSave(
                        $entry->getCanonicalId(),
                        $entry->siteId
                    );
                    return;
                }

                // If we're not propagating, handle normal save logic.
                $this->plugin->getVerification()->handleCanonicalSave($entry);

                // If the entry was edited, notify the entry's assigned Reviewer.
                $this->plugin->getVerification()->handleCheckForChanges($entry);
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