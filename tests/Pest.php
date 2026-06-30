<?php

use markhuot\craftpest\test\RefreshesDatabase;
use markhuot\craftpest\test\TestCase;
use craft\helpers\App;


$databaseName = App::env('CRAFT_DB_DATABASE');
if (! str_contains((string)$databaseName, 'test')) {
    fwrite(STDERR, "Aborting: Pest must run against a dedicated test database. Run via `ddev composer test:verified-elements` or set CRAFT_DB_DATABASE to a database whose name contains 'test'.\n");
    exit(1);
}

/*
|---------------------------------------------------------------------------------------------------
| Test Case
|---------------------------------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "uses()" function to bind a different classes or traits.
|
*/

// UNIT tests
uses(TestCase::class)->in(__DIR__ . '/Unit');

// INTEGRATION tests
uses(TestCase::class, RefreshesDatabase::class)
    ->beforeAll(function () {
        // First run against an empty DB: craft-pest hasn't installed Craft yet
        // (that happens in the first test's setUp). Skip; the re-exec after
        // install comes back through here.
        if (! Craft::$app->getIsInstalled(true)) {
            return;
        }

        getSharedReviewer('a');
        getSharedReviewer('b');
    })
    ->afterEach(function () {
        cleanUpSites();
        cleanUpSections();
        cleanUpUsers();
    })
    ->in(__DIR__ . '/Integration');


/*
|---------------------------------------------------------------------------------------------------
| Expectations
|---------------------------------------------------------------------------------------------------
|
| When you're writing tests, you often need to check that values meet certain conditions. The
| "expect()" function gives you access to a set of "expectations" methods that you can use
| to assert different things. Of course, you may extend the Expectation API at any time.
|
*/


/*
|---------------------------------------------------------------------------------------------------
| Constants
|---------------------------------------------------------------------------------------------------
*/

/**
 * This prefix gets prepended to all fake name/handles for Sections, Sites, as well as fake User
 * email addresses. Its purpose is to easily identify what needs to be deleted in the cleanup
 * after the tests run.
 */
const TEST_PREFIX = 'dxW36b';


/*
|---------------------------------------------------------------------------------------------------
| Functions
|---------------------------------------------------------------------------------------------------
|
| While Pest is very powerful out-of-the-box, you may have some testing code specific to your
| project that you don't want to repeat in every file. Here you can also expose helpers as
| global functions to help you to reduce the number of lines of code in your test files.
|
*/
