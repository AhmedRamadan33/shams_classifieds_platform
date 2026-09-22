<?php

declare(strict_types=1);

use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsGateway;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the dedicated MySQL database configured in phpunit.xml
| (`shams_test`). Each test runs inside a transaction that is rolled back.
|
| Note: InnoDB FULLTEXT indexes only see committed rows, so tests that exercise
| MATCH ... AGAINST use the DatabaseTruncation trait explicitly instead.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

// FULLTEXT search needs committed rows, so these tests truncate tables instead of using a transaction.
pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Search');

/*
|--------------------------------------------------------------------------
| Helpers
|--------------------------------------------------------------------------
*/

/**
 * Swap the SMS gateway for a fake that records messages (and the OTP inside them).
 */
function fakeSms(): FakeSmsGateway
{
    $fake = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $fake);

    return $fake;
}
