<?php

declare(strict_types=1);

use App\Services\Sms\SmsGateway;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeSmsGateway;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

pest()->extend(TestCase::class)
    ->use(DatabaseTruncation::class)
    ->in('Search');

function fakeSms(): FakeSmsGateway
{
    $fake = new FakeSmsGateway;
    app()->instance(SmsGateway::class, $fake);

    return $fake;
}
