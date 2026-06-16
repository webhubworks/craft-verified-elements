<?php

namespace webhubworks\verifiedentries\elements;

use Craft;
use craft\elements\db\EntryQuery;
use craft\elements\Entry;
use craft\elements\User;
use craft\helpers\DateTimeHelper;
use webhubworks\verifiedentries\VerifiedEntries;

/**
 * Verified Entry element type
 */
class VerifiedEntry extends Entry
{
    public static function refHandle(): ?string
    {
        return 'verifiedEntry';
    }

    public static function find(): EntryQuery
    {
        return new EntryQuery(Entry::class);
    }

    protected static function defineDefaultTableAttributes(string $source): array
    {
        return [
            'section',
            'postDate',
            'isVerified',
            'verifiedUntilDate',
            'reviewer',
        ];
    }

    protected static function defineSources(string $context = null): array
    {
        /** @var  $verificationService */
        $plugin = VerifiedEntries::getInstance();
        $enabledSectionIds = $plugin->sectionSettings->getEnabledSections();

        $currentUser = Craft::$app->user;
        $reviewers = User::find()
            ->can('verifyEntries')
            ->id(['not', $currentUser->id])
            ->all();

        $sources = [
            [
                'key' => 'expired',
                'label' => Craft::t('verified-entries', 'Expired'),
                'criteria' => [
                    'isVerified' => false,
                    'sectionId' => $enabledSectionIds,
                ]
            ],
            [
                'key' => 'upcoming',
                'label' => Craft::t('app', 'Pending'),
                'criteria' => [
                    'isVerified' => true,
                    'sectionId' => $enabledSectionIds,
                    'verifiedUntil' => '< ' . (DateTimeHelper::nextMonth())->format('Y-m-d'),
                ]
            ],
            [
                'key' => 'verified',
                'label' => Craft::t('verified-entries', 'Verified'),
                'criteria' => [
                    'isVerified' => true,
                    'sectionId' => $enabledSectionIds,
                ]
            ],
            [
                'heading' => Craft::t('verified-entries', 'Reviewer'),
            ],
            [
                'key' => 'mine',
                'label' => $currentUser->getIdentity()->friendlyName,
                'criteria' => [
                    'reviewerId' => $currentUser->id,
                    'sectionId' => $enabledSectionIds,
                ]
            ]
        ];

        foreach ($reviewers as $reviewer) {
            /** @var User $reviewer */
            $sources[] = [
                'key' => 'reviewer-' . $reviewer->id,
                'label' => $reviewer->friendlyName,
                'criteria' => [
                    'reviewerId' => $reviewer->id,
                    'sectionId' => $enabledSectionIds,
                ],
            ];
        }

        return $sources;
    }
}
