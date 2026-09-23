<?php

declare(strict_types=1);

use App\Actions\ApproveListing;
use App\Actions\RejectListing;
use App\Models\Listing;
use App\Models\Page;
use App\Models\User;
use App\Notifications\ListingApproved;
use App\Notifications\ListingExpiringSoon;
use App\Notifications\ListingRejected;
use Database\Seeders\PageSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

it('notifies the owner when a listing is approved or rejected', function () {
    $owner = User::factory()->create();
    $pending = Listing::factory()->for($owner)->pending()->titled('إعلان سيُوافق عليه')->create();
    $other = Listing::factory()->for($owner)->pending()->titled('إعلان سيُرفض')->create();

    app(ApproveListing::class)($pending);
    app(RejectListing::class)($other, 'الصور غير واضحة');

    $notifications = $owner->notifications;

    expect($notifications)->toHaveCount(2)
        ->and($owner->notifications->pluck('type')->all())->toContain(ListingApproved::class, ListingRejected::class);

    $approved = $owner->notifications->firstWhere('type', ListingApproved::class);
    expect($approved->data['message'])->toContain('إعلان سيُوافق عليه')
        ->and($approved->data['url'])->toBe($pending->fresh()->url());

    $rejected = $owner->notifications->firstWhere('type', ListingRejected::class);
    expect($rejected->data['message'])->toContain('الصور غير واضحة');
});

it('shows notifications, highlights unread ones and marks them read afterwards', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->pending()->titled('إعلان لاختبار الإشعارات')->create();
    app(ApproveListing::class)($listing);

    $this->actingAs($user);

    $this->get('/')->assertSee('data-testid="unread-badge"', false);

    $this->get('/notifications')
        ->assertOk()
        ->assertSee('>'.__('app.notifications.unread').'<', false)
        ->assertSee('أصبح ظاهراً للزوار');

    expect($user->unreadNotifications()->count())->toBe(0);

    $this->get('/')->assertDontSee('data-testid="unread-badge"', false);
    $this->get('/notifications')->assertDontSee('>'.__('app.notifications.unread').'<', false);
});

it('only shows the user\'s own notifications', function () {
    $mine = User::factory()->create();
    $theirs = User::factory()->create();
    app(ApproveListing::class)(Listing::factory()->for($theirs)->pending()->titled('إعلان شخص آخر')->create());

    $this->actingAs($mine)->get('/notifications')
        ->assertOk()
        ->assertDontSee('إعلان شخص آخر')
        ->assertSee(__('app.notifications.empty_title'));
});

it('can mark everything as read', function () {
    $user = User::factory()->create();
    app(ApproveListing::class)(Listing::factory()->for($user)->pending()->create());

    $this->actingAs($user)->post('/notifications/read-all')->assertRedirect();

    expect($user->unreadNotifications()->count())->toBe(0);
});

it('stores an expiring-soon notification pointing at the renew action', function () {
    $user = User::factory()->create();
    $listing = Listing::factory()->for($user)->create(['expires_at' => now()->addDays(2)]);

    $user->notify(new ListingExpiringSoon($listing));

    expect($user->notifications()->sole()->data['message'])->toContain($listing->title)
        ->and($user->notifications()->sole()->data['url'])->toContain('status=active');
});

it('requires login for notifications', function () {
    $this->get('/notifications')->assertRedirect(route('login'));
});

it('seeds the six Arabic static pages without overwriting edits', function () {
    $this->seed(PageSeeder::class);

    expect(Page::orderBy('sort_order')->pluck('title')->all())->toBe(['من نحن', 'الشروط والأحكام', 'سياسة الخصوصية', 'نصائح الأمان', 'الإعلانات المحظورة', 'اتصل بنا'])
        ->and(Page::orderBy('sort_order')->pluck('slug')->all())->toBe(['about', 'terms', 'privacy', 'safety-tips', 'prohibited-ads', 'contact']);

    Page::where('slug', 'about')->update(['body' => 'نص معدّل من الإدارة']);
    $this->seed(PageSeeder::class);

    expect(Page::count())->toBe(6)
        ->and(Page::where('slug', 'about')->value('body'))->toBe('نص معدّل من الإدارة');
});

it('shows a published page at /p/{slug} and 404s for unknown or unpublished ones', function () {
    Page::create(['slug' => 'about', 'title' => 'من نحن', 'body' => "السطر الأول\nالسطر الثاني <b>مهم</b>"]);
    Page::create(['slug' => 'draft', 'title' => 'مسودة', 'body' => 'x', 'is_published' => false]);

    $this->get('/p/about')
        ->assertOk()
        ->assertSee('من نحن')
        ->assertSee('السطر الأول<br />', false)
        ->assertSee('&lt;b&gt;مهم&lt;/b&gt;', false);

    $this->get('/p/draft')->assertNotFound();
    $this->get('/p/missing')->assertNotFound();
});

it('links the published pages from the footer, in order, and updates when they change', function () {
    Page::create(['slug' => 'terms', 'title' => 'الشروط والأحكام', 'body' => 'x', 'sort_order' => 2]);
    Page::create(['slug' => 'about', 'title' => 'من نحن', 'body' => 'x', 'sort_order' => 1]);
    Page::create(['slug' => 'draft', 'title' => 'مسودة مخفية', 'body' => 'x', 'is_published' => false]);

    $this->get('/')
        ->assertSeeInOrder(['من نحن', 'الشروط والأحكام'])
        ->assertSee(route('pages.show', 'about'), false)
        ->assertDontSee('مسودة مخفية');

    Page::where('slug', 'draft')->update(['is_published' => true]);
    Page::query()->where('slug', 'draft')->first()->save();

    $this->get('/')->assertSee('مسودة مخفية');
});

it('uploads a sanitized avatar and shows it in the header', function () {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', [
        'name' => $user->name,
        'avatar' => Fixtures::jpegWithExifOrientation(200, 100),
    ])->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->avatar)->toStartWith('avatars/')->and($user->avatar)->toEndWith('.jpg');
    Storage::disk('public')->assertExists($user->avatar);

    [$width, $height] = getimagesize(Storage::disk('public')->path($user->avatar));
    expect([$width, $height])->toBe([100, 200]);

    $this->get('/')->assertSee($user->avatarUrl(), false);
});

it('replaces and removes the avatar, deleting the old file', function () {
    Storage::fake('public');
    $user = User::factory()->create();
    $this->actingAs($user);

    $this->patch('/profile', ['name' => $user->name, 'avatar' => Fixtures::image('a.jpg', 300, 300)]);
    $first = $user->fresh()->avatar;

    $this->patch('/profile', ['name' => $user->name, 'avatar' => Fixtures::image('b.png', 300, 300)]);
    $second = $user->fresh()->avatar;

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);

    $this->patch('/profile', ['name' => $user->name, 'remove_avatar' => '1']);

    expect($user->fresh()->avatar)->toBeNull();
    Storage::disk('public')->assertMissing($second);
});

it('rejects invalid avatars', function (Closure $file) {
    Storage::fake('public');
    $user = User::factory()->create();

    $this->actingAs($user)->patch('/profile', ['name' => $user->name, 'avatar' => $file()])->assertSessionHasErrors('avatar');

    expect($user->fresh()->avatar)->toBeNull();
})->with([
    'too big' => [fn () => UploadedFile::fake()->image('big.jpg', 300, 300)->size(3000)],
    'not an image' => [fn () => UploadedFile::fake()->createWithContent('x.jpg', 'plain text')],
    'pdf' => [fn () => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')],
]);
