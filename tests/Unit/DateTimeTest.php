<?php

use Carbon\Carbon;
use craft\helpers\Db;
use webhubworks\verifiedelements\helpers\DateHelper;

/**
 * UNIT TESTS
 * @see DateHelper
 *
 * Tests various date/time related methods in the helper.
 */


// now()
// =================================================================================================

it('stores the same verified-until date whether or not now() carries the system timezone', function() {
    $originalTimeZone = Craft::$app->getTimeZone();

    try {
        Craft::$app->setTimeZone('Europe/Berlin');

        // Freeze "now" just before Berlin's autumn DST end (CEST, UTC+2);
        // P30D lands in November after the switch to CET (UTC+1).
        Carbon::setTestNow(Carbon::create(2026, 10, 20, 0, 30, 0, 'UTC'));
        $interval = new DateInterval('P30D');

        $bareNow = Carbon::now();        // what the three Bucket B sites use today
        $helperNow = DateHelper::now();  // what the helper would substitute

        // Diagnostic: this line explains the outcome either way.
        expect($helperNow->getTimezone()->getName())
            ->toBe($bareNow->getTimezone()->getName());

        $bareStored = Db::prepareDateForDb($bareNow->copy()->add($interval));
        $helperStored = Db::prepareDateForDb($helperNow->copy()->add($interval));

        // The real question. Passes => Bucket B is a safe no-op.
        expect($helperStored)->toBe($bareStored);
    } finally {
        Carbon::setTestNow();
        Craft::$app->setTimeZone($originalTimeZone);
    }
});
