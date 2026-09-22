<?php

declare(strict_types=1);

use App\Filament\Resources\StaticPages\Pages\CreatePage;
use App\Filament\Resources\StaticPages\Pages\EditPage;
use App\Filament\Resources\StaticPages\Pages\ListPages;
use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->admin = User::factory()->admin()->create(['name' => 'المدير العام']);
    $this->actingAs($this->admin);
});

// --------------------------------------------------------------------- access

it('keeps users and pages admin only', function () {
    foreach (['users', 'pages'] as $resource) {
        $this->get("/admin/{$resource}")->assertOk();
    }

    $this->actingAs(User::factory()->moderator()->create());

    foreach (['users', 'pages'] as $resource) {
        $this->get("/admin/{$resource}")->assertForbidden();
    }
});

// ---------------------------------------------------------------------- users

it('shows users with roles and listing counts', function () {
    $user = User::factory()->create(['name' => 'مستخدم عادي']);
    Listing::factory()->count(3)->for($user)->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$user, $this->admin])
        ->assertSee('3')
        ->assertSee(__('app.admin.role_names.admin'));
});

it('bans and unbans a user; banned users lose access and their listings disappear', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create();

    auth()->logout();
    $this->get($listing->url())->assertOk();
    $this->actingAs($this->admin);

    Livewire::test(ListUsers::class)->callAction(TestAction::make('toggle_ban')->table($user));

    expect($user->fresh()->is_banned)->toBeTrue();

    auth()->logout();
    $this->get($listing->url())->assertNotFound();
    $this->get('/')->assertDontSee($listing->title);
    $this->get("/seller/{$user->id}")->assertNotFound();

    // the banned user is signed out on their next request and cannot log in
    $this->actingAs($user->fresh())->get('/dashboard')->assertRedirect(route('login'));

    $this->actingAs($this->admin);
    Livewire::test(ListUsers::class)->callAction(TestAction::make('toggle_ban')->table($user->fresh()));

    expect($user->fresh()->is_banned)->toBeFalse();
    auth()->logout();
    $this->get($listing->url())->assertOk();
});

it('does not let an admin ban themselves', function () {
    Livewire::test(ListUsers::class)->assertActionHidden(TestAction::make('toggle_ban')->table($this->admin));
});

it('creates a user: phone normalized and verified, password hashed, role assigned', function () {
    Livewire::test(CreateUser::class)
        ->fillForm([
            'name' => 'مشرف جديد',
            'phone' => '٠١٠٩٨٧٦٥٤٣٢',
            'password' => 'a-strong-pass-1',
            'roles' => [Role::findOrCreate('moderator', 'web')->id],
            'is_banned' => false,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $user = User::where('phone', '+201098765432')->sole();

    expect($user->name)->toBe('مشرف جديد')
        ->and($user->hasVerifiedPhone())->toBeTrue()
        ->and($user->hasRole('moderator'))->toBeTrue()
        ->and($user->hasRole('user'))->toBeFalse()
        ->and(Hash::check('a-strong-pass-1', $user->password))->toBeTrue();
});

it('validates the user form', function () {
    User::factory()->create(['phone' => '+201011112222']);
    $role = Role::findOrCreate('user', 'web');

    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'س', 'phone' => '123', 'password' => 'short', 'roles' => []])
        ->call('create')
        ->assertHasFormErrors(['phone', 'password', 'roles']);

    // the same number in another format is a duplicate
    Livewire::test(CreateUser::class)
        ->fillForm(['name' => 'مكرر', 'phone' => '01011112222', 'password' => 'a-strong-pass-1', 'roles' => [$role->id]])
        ->call('create')
        ->assertHasFormErrors(['phone']);
});

it('changes a user\'s role and keeps the password when the field is left empty', function () {
    $user = User::factory()->create(['password' => 'original-pass-1']);
    $moderator = Role::findOrCreate('moderator', 'web');

    Livewire::test(EditUser::class, ['record' => $user->getRouteKey()])
        ->fillForm(['roles' => [$moderator->id], 'password' => ''])
        ->call('save')
        ->assertHasNoFormErrors();

    $user->refresh();

    expect($user->hasRole('moderator'))->toBeTrue()
        ->and($user->isStaff())->toBeTrue()
        ->and(Hash::check('original-pass-1', $user->password))->toBeTrue();
});

// ---------------------------------------------------------------------- pages

it('creates, edits and deletes static pages', function () {
    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'صفحة جديدة', 'slug' => 'new-page', 'body' => "سطر\nثاني", 'is_published' => true, 'sort_order' => 1])
        ->call('create')
        ->assertHasNoFormErrors();

    $page = Page::where('slug', 'new-page')->sole();
    $this->get('/p/new-page')->assertOk()->assertSee('صفحة جديدة');

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])
        ->fillForm(['title' => 'عنوان معدّل', 'is_published' => false])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($page->fresh()->title)->toBe('عنوان معدّل');
    $this->get('/p/new-page')->assertNotFound();

    Livewire::test(EditPage::class, ['record' => $page->getRouteKey()])->callAction('delete');

    expect(Page::where('slug', 'new-page')->exists())->toBeFalse();
});

it('validates static page slugs', function (string $slug) {
    Livewire::test(CreatePage::class)
        ->fillForm(['title' => 'عنوان', 'slug' => $slug, 'body' => 'نص', 'sort_order' => 0])
        ->call('create')
        ->assertHasFormErrors(['slug']);
})->with(['arabic' => ['صفحة'], 'uppercase' => ['About'], 'spaces' => ['a b']]);

it('lists the pages for the admin', function () {
    $page = Page::create(['slug' => 'about', 'title' => 'من نحن', 'body' => 'x']);

    Livewire::test(ListPages::class)->assertCanSeeTableRecords([$page]);
});
