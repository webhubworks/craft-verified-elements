<?php

use craft\elements\Entry;
use Mockery\MockInterface;
use webhubworks\verifiedentries\models\SectionDefaults;
use webhubworks\verifiedentries\services\VerificationFieldsSetter;
use webhubworks\verifiedentries\services\singletons\PluginSettings;
use webhubworks\verifiedentries\models\ExpiredEntryData;
use webhubworks\verifiedentries\services\ExpiredVerificationNotifier;


/**
 * Wrapper class to bypass DB-dependent setter methods during unit testing.
 */
class TestableExpiredVerificationNotifier extends ExpiredVerificationNotifier
{
    protected function setExpiredEntries(): void {}

    public function seed(array $byReviewer, array $unassigned): void
    {
        $reflection = new ReflectionClass(ExpiredVerificationNotifier::class);
        $reflection->getProperty('expiredEntriesByReviewerId')->setValue($this, $byReviewer);
        $reflection->getProperty('expiredUnassignedEntries')->setValue($this, $unassigned);
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
    $settings->allows('isSectionEnabledForSite')->andReturn($sectionEnabled);

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
 * Generates a mock Craft entry for unit testing.
 *
 * @param int $sectionId
 * @param int $siteId
 * @return Entry|MockInterface
 */
function mockEntry(int $sectionId = 1, int $siteId = 1): Entry|MockInterface
{
    $entry = Mockery::mock(Entry::class);
    $entry->sectionId = $sectionId;
    $entry->siteId = $siteId;
    return $entry;
}

/**
 * Generate the plugin's ExpiredEntryData object for unit testing.
 *
 * @param int|null $reviewerId
 * @return ExpiredEntryData
 */
function mockExpiredEntry(?int $reviewerId = 1): ExpiredEntryData
{
    return new ExpiredEntryData(
        100,
        1,
        $reviewerId,
        '2020-01-01 00:00:00',
        1,
        'Test Entry',
        'testSection',
        'default',
    );
}
