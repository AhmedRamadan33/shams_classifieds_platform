<?php

declare(strict_types=1);

use App\Models\SavedSearch;
use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->token = $this->user->createToken('test')->plainTextToken;
});

it('saves a search, lists it with a live count, toggles notify and deletes it', function () {
    $this->withToken($this->token)
        ->postJson('/api/v1/my/saved-searches', ['name' => 'بحثي المحفوظ', 'notify' => true, 'q' => 'شقة'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'بحثي المحفوظ')
        ->assertJsonPath('data.notify', true);

    $search = SavedSearch::sole();

    $this->withToken($this->token)
        ->getJson('/api/v1/my/saved-searches')
        ->assertOk()
        ->assertJsonPath('data.0.id', $search->id)
        ->assertJsonStructure(['data' => [['current_count']]]);

    $this->withToken($this->token)
        ->patchJson("/api/v1/my/saved-searches/{$search->id}", ['notify' => false])
        ->assertOk()
        ->assertJsonPath('data.notify', false);

    $this->withToken($this->token)->deleteJson("/api/v1/my/saved-searches/{$search->id}")->assertOk();
    expect(SavedSearch::count())->toBe(0);
});

it('forbids managing someone else\'s saved search', function () {
    $search = SavedSearch::factory()->create();

    $this->withToken($this->token)->patchJson("/api/v1/my/saved-searches/{$search->id}", ['notify' => true])->assertForbidden();
    $this->withToken($this->token)->deleteJson("/api/v1/my/saved-searches/{$search->id}")->assertForbidden();
});

it('requires authentication for saved searches', function () {
    $this->getJson('/api/v1/my/saved-searches')->assertUnauthorized();
    $this->postJson('/api/v1/my/saved-searches', ['name' => 'x'])->assertUnauthorized();
});
