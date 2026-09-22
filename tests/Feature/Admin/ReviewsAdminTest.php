<?php

declare(strict_types=1);

use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Models\Review;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create();
});

it('lets admins and moderators see reviews, but keeps regular users out', function () {
    $this->actingAs($this->admin)->get('/admin/reviews')->assertOk();
    $this->actingAs(User::factory()->moderator()->create())->get('/admin/reviews')->assertOk();
    $this->actingAs(User::factory()->create())->get('/admin/reviews')->assertForbidden();
});

it('never offers creating a review in the panel', function () {
    $this->actingAs($this->admin)->get('/admin/reviews/create')->assertNotFound();
});

it('lists reviews with the seller, reviewer and rating', function () {
    $this->actingAs($this->admin);
    $review = Review::factory()->create([
        'seller_id' => User::factory()->create(['name' => 'بائع'])->id,
        'reviewer_id' => User::factory()->create(['name' => 'مقيِّم'])->id,
    ]);

    Livewire::test(ListReviews::class)->assertCanSeeTableRecords([$review])->assertSee('بائع')->assertSee('مقيِّم');
});

it('hides and unhides a review from the panel', function () {
    $this->actingAs($this->admin);
    $review = Review::factory()->create(['is_hidden' => false]);

    Livewire::test(ListReviews::class)
        ->callAction(TestAction::make('hide')->table($review))
        ->assertNotified();

    expect($review->fresh()->is_hidden)->toBeTrue();

    Livewire::test(ListReviews::class)
        ->callAction(TestAction::make('unhide')->table($review))
        ->assertNotified();

    expect($review->fresh()->is_hidden)->toBeFalse();
});
