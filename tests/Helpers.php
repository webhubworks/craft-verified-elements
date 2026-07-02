<?php

use Mockery\MockInterface;
use Random\RandomException;
use craft\elements\Entry;
use craft\elements\User;
use craft\elements\db\EntryQuery;
use craft\errors\SiteNotFoundException;
use craft\helpers\StringHelper;
use craft\models\Section;
use craft\models\Site;
use webhubworks\verifiedelements\behaviors\VerifiableBehavior;
use webhubworks\verifiedelements\behaviors\VerifiableQueryBehavior;
use webhubworks\verifiedelements\helpers\Log;
use webhubworks\verifiedelements\models\ElementData;
use webhubworks\verifiedelements\models\SectionDefaults;
use webhubworks\verifiedelements\services\ExpiredVerificationNotifier;
use webhubworks\verifiedelements\services\VerificationFieldsSetter;
use webhubworks\verifiedelements\services\singletons\PluginSettings;



/*
|---------------------------------------------------------------------------------------------------
| Post-Tests Cleanup
|---------------------------------------------------------------------------------------------------
|
| These functions will run in the the `afterEach` hook to undo any created models/elements.
*/

/**
 * Revert changes to Site models after tests conclude.
 *
 * @return void
 */
function cleanUpSites(): void
{
    $sites = Craft::$app->getSites();
    foreach ($sites->getAllSites() as $site) {
        if (! str_starts_with($site->handle, TEST_PREFIX)) {
            continue;
        }

        try {
            $sites->deleteSite($site);
        }
        catch (Throwable $exception) {
            Log::error(sprintf(
                'Error deleting Site [%s] "%s":',
                $site->id,
                $site->getName()
            ), $exception);
        }
    }
}

/**
 * Revert changes to Section models after tests conclude.
 *
 * @return void
 */
function cleanUpSections(): void
{
    $entries = Craft::$app->getEntries();
    foreach ($entries->getAllSections() as $section) {
        if (!str_starts_with($section->handle, TEST_PREFIX)) {
            continue;
        }

        try {
            $entries->deleteSection($section);
        }
        catch (Throwable $exception) {
            Log::error(sprintf(
                'Error deleting Section [%s] "%s":',
                $section->id,
                $section->name
            ), $exception);
        }
    }
}

/**
 * Revert changes to User elements after tests conclude.
 *
 * @return void
 */
function cleanUpUsers(): void
{
    $elements = Craft::$app->getElements();
    foreach (User::find()->email(TEST_PREFIX . '*')->all() as $user) {
        /** @var User $user */
        if (!str_starts_with($user->email, TEST_PREFIX)) {
            continue;
        }

        try {
            $elements->deleteElement($user);
        }
        catch (Throwable $exception) {
            Log::error(sprintf(
                'Error deleting User [%s] "%s":',
                $user->getId(),
                $user->getName()
            ), $exception);
        }
    }
}



/*
|---------------------------------------------------------------------------------------------------
| UNIT Test Helpers
|---------------------------------------------------------------------------------------------------
*/

/**
 * Wrapper class to bypass DB-dependent setter methods during unit testing.
 */
class TestableExpiredVerificationNotifier extends ExpiredVerificationNotifier
{
    protected function setExpiredElements(): void {}

    public function seed(array $byReviewer, array $unassigned): void
    {
        $reflection = new ReflectionClass(ExpiredVerificationNotifier::class);
        $reflection->getProperty('expiredElementsByReviewerId')->setValue($this, $byReviewer);
        $reflection->getProperty('expiredUnassignedElements')->setValue($this, $unassigned);
    }
}

/**
 * Generate a mock of the PluginSettings service singleton for unit testing.
 *
 * @param bool $sectionEnabled
 * @param int|null $reviewerId
 * @param string|null $period
 * @return PluginSettings
 */
function mockPluginSettings(bool $sectionEnabled = true, ?int $reviewerId = null, ?string $period = null): PluginSettings
{
    /** @var PluginSettings|MockInterface $settings */
    $settings = Mockery::mock(PluginSettings::class);

    $defaults = null;
    if ($reviewerId !== null || $period !== null) {
        $defaults = new SectionDefaults(
            1,
            'Test',
            'test',
            1,
            $reviewerId,
            $period
        );
    }

    $settings->allows('getDefaultSettingsForSection')->andReturn($defaults);
    $settings->allows('isContainerEnabledForSite')->andReturn($sectionEnabled);

    return $settings;
}

/**
 * Generate a mock of the plugin's VerificationFieldsSetter service for unit testing.
 *
 * @param int|null $currentReviewerId
 * @param DateTime|null $currentVerifiedUntilDate
 * @param bool $isFirstSave
 * @param int|null $defaultReviewerId
 * @param string|null $defaultPeriod
 * @return VerificationFieldsSetter
 */
function mockVerificationFieldsSetter(
    ?int      $currentReviewerId,
    ?DateTime $currentVerifiedUntilDate,
    bool      $isFirstSave,
    ?int      $defaultReviewerId,
    ?string   $defaultPeriod,
): VerificationFieldsSetter
{
    return new VerificationFieldsSetter(
        1,
        1,
        $currentReviewerId,
        $currentVerifiedUntilDate,
        $isFirstSave,
        mockPluginSettings(
            true,
            $defaultReviewerId,
            $defaultPeriod
        ),
    );
}

/**
 * Generate the plugin's ElementData object for unit testing.
 *
 * @param int|null $reviewerId
 * @param string|null $verifiedUntilDate Raw DB value (UTC); null means "Indefinitely".
 * @param int $id
 * @param int $siteId
 * @param int $containerId
 * @return ElementData
 */
function mockElementData(
    ?int    $reviewerId = null,
    ?string $verifiedUntilDate = null,
    int     $id = 1,
    int     $siteId = 1,
    int     $containerId = 1,
): ElementData
{
    return new ElementData(
        type: Entry::class,
        rowId: null,
        id: $id,
        title: 'Test Entry',
        siteId: $siteId,
        siteName: 'Default',
        siteHandle: 'default',
        containerId: $containerId,
        containerName: 'Test',
        containerHandle: 'test',
        dateUpdated: null,
        reviewerId: $reviewerId,
        verifiedUntilDate: $verifiedUntilDate,
        cpEditUrl: '',
    );
}




/*
|---------------------------------------------------------------------------------------------------
| INTEGRATION Test Helpers
|---------------------------------------------------------------------------------------------------
*/

/**
 * Generate a unique handle that can be identified as fake when deleting items after the tests run.
 *
 * @param string $type
 * @param string|null $name
 * @return string
 * @throws RandomException
 */
function pestHandle(string $type, ?string $name = null): string
{
    $parts = [
        TEST_PREFIX, // so we can identify all test items and delete them after the tests run
        $type
    ];

    if ($name) {
        $parts[] = StringHelper::toSnakeCase($name);
    }

    // ensure uniqueness so there are no collisions in the db
    $parts[] = bin2hex(random_bytes(2));

    return implode('_', $parts);
}


/**
 * Helper for ensuring entries have the behavior class that the plugin normally directs Craft to
 * attach to all entries.
 *
 * @param Entry $entry
 * @return Entry|VerifiableBehavior
 */
function withVerifiableBehavior(Entry $entry): Entry|VerifiableBehavior
{
    $entry->attachBehavior(VerifiableBehavior::NAME, VerifiableBehavior::class);

    return $entry;
}

/**
 * Helper for ensuring entry queries have the behavior class that the plugin normally directs
 * Craft to attach to all entries.
 *
 * @param EntryQuery $query
 * @return EntryQuery|VerifiableQueryBehavior
 */
function withVerifiableQueryBehavior(EntryQuery $query): EntryQuery|VerifiableQueryBehavior
{
    $query->attachBehavior(VerifiableQueryBehavior::NAME, VerifiableQueryBehavior::class);

    return $query;
}

/**
 * Wrapper for generating throwaway Section models with which to test.
 *
 * @return Section
 * @throws RandomException
 */
function createSection(): Section
{
    return \markhuot\craftpest\factories\Section::factory()
        ->name(pestHandle('section'))
        ->create();
}

/**
 * Wrapper for generating throwaway Site models with which to test.
 *
 * @param string $name
 * @return Site
 * @throws Throwable
 * @throws SiteNotFoundException
 */
function createSite(string $name): Site
{
    $handle = pestHandle('site', $name);

    $primarySite = Craft::$app->getSites()->getPrimarySite();

    $site = new Site([
        'groupId' => $primarySite->groupId,
        'name' => $handle,
        'handle' => $handle,
        'language' => 'en-US',
        'baseUrl' => '@web/' . $handle,
    ]);

    $isSaved = Craft::$app->getSites()->saveSite($site);
    if (! $isSaved) {
        throw new RuntimeException('Failed to save site: ' . implode(', ', $site->getFirstErrors()));
    }

    return $site;
}

/**
 * Returns a Craft `User` object from Pest's factory.
 *
 * Calling this will save the user to the testing database for reuse across all tests. It's
 * deliberately NOT prefixed with TEST_PREFIX so the afterEach sweeps never delete them.
 * Treat as immutable: tests may reference them (e.g. assign as reviewer) but never modify or
 * delete them.
 *
 * @param string $name
 * @return User
 */
function getSharedReviewer(string $name = 'a'): User
{
    $email = sprintf('pest.shared.reviewer.%s@test.com', $name);

    $user = User::find()->email($email)->one();
    if ($user !== null) {
        return $user;
    }

    return \markhuot\craftpest\factories\User::factory()
        ->sequence(['email' => $email])
        ->create();
}
