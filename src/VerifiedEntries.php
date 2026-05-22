<?php

namespace webhubworks\verifiedentries;

use Craft;
use craft\base\Element;
use craft\base\Event;
use craft\base\Plugin;
use craft\base\conditions\BaseCondition;
use craft\controllers\UsersController;
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
use craft\helpers\UrlHelper;
use craft\services\Dashboard;
use craft\services\Elements;
use craft\services\Gc;
use craft\services\UserPermissions;
use craft\validators\DateTimeValidator;
use craft\web\UrlManager;
use webhubworks\verifiedentries\behaviors\EntryBehavior;
use webhubworks\verifiedentries\behaviors\EntryQueryBehavior;
use webhubworks\verifiedentries\elements\VerifiedEntry;
use webhubworks\verifiedentries\elements\actions\AssignReviewer;
use webhubworks\verifiedentries\elements\actions\VerifyEntry;
use webhubworks\verifiedentries\elements\conditions\ReviewerConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedConditionRule;
use webhubworks\verifiedentries\elements\conditions\VerifiedUntilDateConditionRule;
use webhubworks\verifiedentries\enums\Permission;
use webhubworks\verifiedentries\services\Notifications as NotificationsService;
use webhubworks\verifiedentries\services\SectionSettings as SectionSettingsService;
use webhubworks\verifiedentries\services\Reviewers as VerifiedEntriesUsersService;
use webhubworks\verifiedentries\services\Verification as VerificationService;
use webhubworks\verifiedentries\widgets\EntriesToReview;
use webhubworks\verifiedentries\widgets\VerificationHealth;

/**
 * Verified Entries plugin
 *
 * @method static VerifiedEntries getInstance()
 * @author webhubworks <support@webhub.de>
 * @copyright webhubworks
 * @license https://craftcms.github.io/license/ Craft License
 * @property-read NotificationsService $notifications
 * @property-read SectionSettingsService $sectionSettings
 * @property-read VerificationService $verification
 * @property-read VerifiedEntriesUsersService $users
 * @property-read null $settingsResponse
 * @property-read null|array $cpNavItem
 */
class VerifiedEntries extends Plugin
{
    public const HANDLE = 'verified-entries';

    public string $schemaVersion = '1.0.0';

    public bool $hasCpSettings = true;

    public bool $hasCpSection = true;

    public static function config(): array
    {
        return [
            'components' => [
                'notifications' => NotificationsService::class,
                'sectionSettings' => SectionSettingsService::class,
                'users' => VerifiedEntriesUsersService::class,
                'verification' => VerificationService::class,
            ],
        ];
    }

    public function init(): void
    {
        parent::init();

        $this->name = Craft::t(self::HANDLE, 'Verified Entries');

        if (Craft::$app->getRequest()->getIsCpRequest()) {
            $this->registerCpRoutes();
        }

        Event::on(
            EntryCondition::class,
            BaseCondition::EVENT_REGISTER_CONDITION_RULES,
            function (RegisterConditionRulesEvent $event) {
                $event->conditionRules[] = VerifiedConditionRule::class;
                $event->conditionRules[] = VerifiedUntilDateConditionRule::class;
                $event->conditionRules[] = ReviewerConditionRule::class;
            }
        );

        Event::on(
            Gc::class,
            Gc::EVENT_RUN,
            function (\yii\base\Event $event) {
                $this->getVerification()->checkExpiredVerifications();
            }
        );

        Craft::$app->onInit(function () {
            $this->attachEventHandlers();
            Craft::$app->getView()->getTwig()->addGlobal('pluginHandle', self::HANDLE);
        });
    }

    public function getNotifications(): NotificationsService
    {
        return $this->get('notifications', new NotificationsService());
    }

    public function getSectionSettings(): SectionSettingsService
    {
        return $this->get('sectionSettings', new SectionSettingsService());
    }

    public function getUsers(): VerifiedEntriesUsersService
    {
        return $this->get('users', new VerifiedEntriesUsersService());
    }

    public function getVerification(): VerificationService
    {
        return $this->get('verification', new VerificationService());
    }

    private function attachEventHandlers(): void
    {
        if (Craft::$app->getRequest()->getIsConsoleRequest()) {
            return;
        }

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_RULES,
            function (DefineRulesEvent $event) {
                $event->rules[] = [['reviewerId'], 'number', 'integerOnly' => true];
                $event->rules[] = [['verifiedUntilDate'], DateTimeValidator::class];
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_BEHAVIORS,
            function ($event) {
                $event->behaviors[self::HANDLE . '.entry'] = EntryBehavior::class;
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_METADATA,
            function (DefineMetadataEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                if (!$entry->getHasVerifiedUntilDate()) {
                    $status = Cp::statusIndicatorHtml('unverified', [
                            'color' => Color::Gray,
                        ]) . Html::tag('span', Craft::t(self::HANDLE, 'Unverified'));;
                } elseif ($entry->isVerified) {
                    $status = Cp::statusIndicatorHtml('live', [
                            'color' => Color::Teal,
                        ]) . Html::tag('span', Craft::t(self::HANDLE, 'Verified'));;
                } else {
                    $status = Cp::statusIndicatorHtml('expired', [
                            'color' => Color::Red,
                        ]) . Html::tag('span', Craft::t(self::HANDLE, 'Expired'));
                }

                $event->metadata[Craft::t(self::HANDLE, 'Verification')] = $status;
            }
        );

        Event::on(
            EntryQuery::class,
            EntryQuery::EVENT_DEFINE_BEHAVIORS,
            function ($event) {
                /** @var EntryQuery $query */

                $event->behaviors[] = EntryQueryBehavior::class;
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_DEFINE_SIDEBAR_HTML,
            function (DefineHtmlEvent $event) {
                /** @var Entry|EntryBehavior $entry */
                $entry = $event->sender;
                $currentUser = Craft::$app->user;

                if (
                    !$entry->getIsSectionEnabledForVerification() ||
                    (!$currentUser->getIsAdmin() && !$currentUser->checkPermission(Permission::VerifyEntries->value))
                ) {
                    return;
                }

                if (!$entry->isVerified) {
                    $event->html .=
                        Html::beginTag('div', ['class' => ['meta', 'warning']]) .
                        Html::tag('p', Craft::t(self::HANDLE, 'Entry has expired and is due to be verified.')) .
                        Html::endTag('div');
                }

                $verification = $this->getVerification();

                $event->html .= Craft::$app->getView()->renderTemplate(
                    self::HANDLE . '/_sidebar.twig',
                    [
                        'addOptionFn' => $verification->getAddOptionFn(),
                        'verifiedUntilDate' => $entry->getVerifiedUntilDate(),
                        'isVerified' => $entry->getIsVerified(),
                        'reviewer' => $entry->getReviewer(),
                        'options' => $verification->getDateOptionsForEntry(
                            $entry->getVerifiedUntilDate(),
                            $entry->sectionId
                        ),
                    ]
                );
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_REGISTER_SORT_OPTIONS,
            function (RegisterElementSortOptionsEvent $event) {
                $event->sortOptions[] = [
                    'label' => Craft::t(self::HANDLE, 'Verified until'),
                    'orderBy' => 'verifiedUntilDate',
                    'defaultDir' => 'desc',
                ];
            }
        );

        Event::on(
            Entry::class,
            Entry::EVENT_REGISTER_TABLE_ATTRIBUTES,
            function (RegisterElementTableAttributesEvent $event) {
                $event->tableAttributes['verifiedUntilDate'] = [
                    'label' => Craft::t(self::HANDLE, 'Verified until')
                ];

                $event->tableAttributes['isVerified'] = [
                    'label' => Craft::t(self::HANDLE, 'Verification'),
                ];

                $event->tableAttributes['reviewer'] = [
                    'label' => Craft::t(self::HANDLE, 'Reviewer'),
                ];
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_ATTRIBUTE_HTML,
            function (DefineAttributeHtmlEvent $event) {
                /** @var Entry $entry */
                $entry = $event->sender;

                switch ($event->attribute) {
                    case "isVerified":
                        if ($entry->isVerified) {
                            $event->html = Cp::statusLabelHtml([
                                'color' => Color::Teal,
                                'label' => Craft::t(self::HANDLE, 'Verified')
                            ]);
                        } else {
                            $event->html = Cp::statusLabelHtml([
                                'color' => Color::Red,
                                'label' => Craft::t(self::HANDLE, 'Expired'),
                            ]);
                        }
                        break;
                    case "verifiedUntilDate":
                        if ($entry->verifiedUntilDate === null) {
                            $event->html = Craft::t(self::HANDLE, 'Indefinitely');
                        }
//                        else {
//                            $difference = date_diff(DateTimeHelper::now(), $entry->verifiedUntilDate);
//
//                            $event->html = DateTimeHelper::humanDuration($difference, false);
//                        }
                        break;
                    case "reviewer":
                        if ($entry->reviewer) {
                            $event->html = Cp::elementChipHtml($entry->reviewer);
                        }
                        break;
                }
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_DEFINE_INLINE_ATTRIBUTE_INPUT_HTML,
            function (DefineAttributeHtmlEvent $event) {
                /** @var Entry|EntryBehavior $entry */
                $entry = $event->sender;
                $currentUser = Craft::$app->user;
                $canVerifyEntries = $currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::VerifyEntries->value);


                if ($event->attribute === 'reviewer') {
                    $event->html = Cp::elementSelectHtml([
                        'id' => 'reviewerId',
                        'name' => 'reviewerId',
                        'label' => Craft::t(self::HANDLE, 'Reviewer'),
                        'single' => true,
                        'elementType' => User::class,
                        'elements' => $entry->reviewer ? [$entry->reviewer] : null,
                        'criteria' => [
                            'status' => 'active',
                            'can' => Permission::VerifyEntries->value,
                        ],
                        'disabled' => !$canVerifyEntries,
                    ]);
                    return;
                }

                if ($event->attribute === 'verifiedUntilDate') {
                    $verification = $this->getVerification();
                    $event->html = Cp::selectizeFieldHtml([
                        'id' => 'verifiedUntilDate',
                        'name' => 'verifiedUntilDate',
                        'options' => $verification->getDateOptionsForEntry(
                            $entry->getVerifiedUntilDate(),
                            $entry->getSection()->id
                        ),
                        'selectizeOptions' => [
                            'allowEmptyOption' => false,
                            'autocomplete' => false,
                        ],
                        'value' => $entry->getVerifiedUntilDate() ? $entry->getVerifiedUntilDate()->format('Y-m-d') : false,
                        'addOptionLabel' => 'specificDate',
                        'addOptionFn' => $verification->getAddOptionFn(),
                        'disabled' => !$canVerifyEntries,
                    ]);
                }
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_REGISTER_ACTIONS,
            function (RegisterElementActionsEvent $event) {
                $currentUser = Craft::$app->user;

                if ($currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::VerifyEntries->value)) {
                    $event->actions[] = VerifyEntry::class;
                    $event->actions[] = AssignReviewer::class;
                }
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_AFTER_SAVE,
            function (ModelEvent $event) {
                /** @var Entry|EntryBehavior $entry */
                $entry = $event->sender;

                if (!$entry->getHasVerifiedUntilDate() || !$entry->enabled) {
                    return;
                }

                if (! ElementHelper::isRevision($entry)) {
                    return;
                }

                $reviewer = $entry->getReviewer();
                if (!$reviewer || !$reviewer->active) {
                    Craft::info('Entry has no reviewer to notify', __METHOD__);
                    return;
                }

                $creatorId = $entry->getBehavior('revision')->creatorId;
                if ($reviewer->id === $creatorId) {
                    return;
                }

                $this->getNotifications()->sendChangeNotification($entry, $reviewer);
            }
        );

        Event::on(
            Entry::class,
            Element::EVENT_BEFORE_SAVE,
            function (ModelEvent $event) {
                if (!$event->isNew) {
                    return;
                }

                /** @var Entry $entry */
                $entry = $event->sender;

                if ($entry->sectionId === null) {
                    return;
                }

                [$reviewerId, $defaultPeriod] = $this->sectionSettings->getDefaultSettingsForSection($entry->sectionId);

                if ($entry->reviewerId === null && $reviewerId) {
                    $entry->setReviewerId($reviewerId);
                }

                if ($entry->verifiedUntilDate === null && $defaultPeriod) {
                    $dateInterval = new \DateInterval($defaultPeriod);
                    $verifiedUntilDate = DateTimeHelper::now()->add($dateInterval);
                    $entry->setVerifiedUntilDate($verifiedUntilDate);
                }
            }
        );

        Event::on(
            UsersController::class,
            UsersController::EVENT_DEFINE_EDIT_SCREENS,
            function (DefineEditUserScreensEvent $event) {
                if (!$event->editedUser->can(Permission::VerifyEntries->value)) {
                    return;
                }

                $event->screens[self::HANDLE] = [
                    'label' => Craft::t(self::HANDLE, 'Verified Entries'),
                ];
            }
        );

        Event::on(
            Dashboard::class,
            Dashboard::EVENT_REGISTER_WIDGET_TYPES,
            [$this, 'registerWidgets']
        );

        Event::on(Elements::class, Elements::EVENT_REGISTER_ELEMENT_TYPES, function (RegisterComponentTypesEvent $event) {
            $event->types[] = VerifiedEntry::class;
        });

        Event::on(
            UserPermissions::class,
            UserPermissions::EVENT_REGISTER_PERMISSIONS,
            [$this, 'registerPermissions']
        );
    }

    public function getCpNavItem(): ?array
    {
        $nav = parent::getCpNavItem();
        $currentUser = Craft::$app->user;

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

    private function registerCpRoutes(): void
    {
        Event::on(
            UrlManager::class,
            UrlManager::EVENT_REGISTER_CP_URL_RULES,
            function (RegisterUrlRulesEvent $event) {
                $currentUser = Craft::$app->user;

                $event->rules[self::HANDLE] = self::HANDLE . '/entries/index';

                if ($currentUser->getIsAdmin() || $currentUser->checkPermission(Permission::ManageVerificationSettings->value)) {
                    $event->rules[self::HANDLE . '/settings'] = self::HANDLE . '/section-settings/index';
                }

                // User edit screen
                $event->rules['myaccount/' . self::HANDLE] = self::HANDLE . '/users/index';
                $event->rules['users/<userId:\d+>/' . self::HANDLE] = self::HANDLE . '/users/index';
            }
        );
    }

    public function registerPermissions(RegisterUserPermissionsEvent $event): void
    {
        $event->permissions[] = [
            'heading' => Craft::t(self::HANDLE, 'Verified Entries'),
            'permissions' => [
                Permission::ManageVerificationSettings->value => [
                    'label' => Craft::t(self::HANDLE, 'Manage Verification Settings'),
                ],
                Permission::VerifyEntries->value => [
                    'label' => Craft::t(self::HANDLE, 'Verify entries'),
                ]
            ],
        ];
    }

    public function registerWidgets(RegisterComponentTypesEvent $event): void
    {
        $currentUser = Craft::$app->user;

        $event->types[] = VerificationHealth::class;

        if ($currentUser->checkPermission(Permission::VerifyEntries->value)) {
            $event->types[] = EntriesToReview::class;
        }
    }

    public function getSettingsResponse(): null
    {
        // Redirect to our settings page
        Craft::$app->controller->redirect(
            UrlHelper::cpUrl(self::HANDLE . '/settings')
        );

        return null;
    }
}
