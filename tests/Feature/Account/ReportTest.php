<?php

declare(strict_types=1);

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;

beforeEach(function () {
    $this->owner = User::factory()->create();
    $this->listing = Listing::factory()->for($this->owner)->create();
    $this->reporter = User::factory()->create();
});

it('lets a signed in user report a public listing', function () {
    $this->actingAs($this->reporter)
        ->post("/ad/{$this->listing->id}/report", ['reason' => 'scam', 'note' => 'طلب مني تحويل مبلغ مقدماً'])
        ->assertSessionHasNoErrors()
        ->assertSessionHas('success', __('app.report.thanks'));

    $report = Report::sole();

    expect($report->listing_id)->toBe($this->listing->id)
        ->and($report->user_id)->toBe($this->reporter->id)
        ->and($report->reason)->toBe(ReportReason::Scam)
        ->and($report->status)->toBe(ReportStatus::Open)
        ->and($report->note)->toBe('طلب مني تحويل مبلغ مقدماً');
});

it('allows only one report per user per listing', function () {
    $this->actingAs($this->reporter);

    $this->post("/ad/{$this->listing->id}/report", ['reason' => 'scam']);
    $this->post("/ad/{$this->listing->id}/report", ['reason' => 'duplicate'])
        ->assertSessionHas('error', __('app.report.already'));

    expect(Report::count())->toBe(1)
        ->and(Report::sole()->reason)->toBe(ReportReason::Scam);
});

it('lets different users report the same listing', function () {
    $this->actingAs($this->reporter)->post("/ad/{$this->listing->id}/report", ['reason' => 'scam']);
    $this->actingAs(User::factory()->create())->post("/ad/{$this->listing->id}/report", ['reason' => 'sold']);

    expect(Report::count())->toBe(2);
});

it('enforces uniqueness in the database too', function () {
    Report::create(['listing_id' => $this->listing->id, 'user_id' => $this->reporter->id, 'reason' => 'scam']);

    expect(fn () => Report::create(['listing_id' => $this->listing->id, 'user_id' => $this->reporter->id, 'reason' => 'other']))
        ->toThrow(UniqueConstraintViolationException::class);
});

it('validates the reason and requires a note for "other"', function () {
    $this->actingAs($this->reporter);

    $this->post("/ad/{$this->listing->id}/report", [])->assertSessionHasErrorsIn('report', 'reason');
    $this->post("/ad/{$this->listing->id}/report", ['reason' => 'weird'])->assertSessionHasErrorsIn('report', 'reason');
    $this->post("/ad/{$this->listing->id}/report", ['reason' => 'other'])->assertSessionHasErrorsIn('report', 'note');
    $this->post("/ad/{$this->listing->id}/report", ['reason' => 'other', 'note' => str_repeat('ع', 1001)])->assertSessionHasErrorsIn('report', 'note');

    expect(Report::count())->toBe(0);

    $this->post("/ad/{$this->listing->id}/report", ['reason' => 'other', 'note' => 'سبب مفصل للبلاغ'])->assertSessionHasNoErrors();
    expect(Report::count())->toBe(1);
});

it('requires login', function () {
    $this->post("/ad/{$this->listing->id}/report", ['reason' => 'scam'])->assertRedirect(route('login'));
    expect(Report::count())->toBe(0);
});

it('does not let owners report their own listing', function () {
    $this->actingAs($this->owner)
        ->post("/ad/{$this->listing->id}/report", ['reason' => 'scam'])
        ->assertSessionHas('error', __('app.report.own_listing'));

    expect(Report::count())->toBe(0);
});

it('only accepts reports about public listings', function () {
    $pending = Listing::factory()->pending()->create();

    $this->actingAs($this->reporter)->post("/ad/{$pending->id}/report", ['reason' => 'scam'])->assertNotFound();
});

it('throttles report submissions', function () {
    $this->actingAs($this->reporter);
    $listings = Listing::factory()->count(11)->create();

    foreach ($listings->take(10) as $listing) {
        $this->post("/ad/{$listing->id}/report", ['reason' => 'scam'])->assertSessionHasNoErrors();
    }

    $this->post("/ad/{$listings->last()->id}/report", ['reason' => 'scam'])->assertStatus(429);
});

it('shows the report button to signed in visitors but not to the owner', function () {
    $this->actingAs($this->reporter)->get($this->listing->url())->assertSee(__('app.report.button'))->assertSee(route('listings.report', $this->listing), false);

    auth()->logout();
    $this->get($this->listing->url())->assertSee(__('app.report.login'));

    $this->actingAs($this->owner)->get($this->listing->url())->assertDontSee(__('app.report.button'));
});
