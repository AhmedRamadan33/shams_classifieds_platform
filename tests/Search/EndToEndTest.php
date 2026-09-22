<?php

declare(strict_types=1);

use App\Enums\ListingStatus;
use App\Enums\ReportStatus;
use App\Filament\Resources\Listings\Pages\ListListings;
use App\Filament\Resources\Reports\Pages\ListReports;
use App\Models\Category;
use App\Models\City;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Support\Fixtures;

/*
 * The manual scenarios of the plan's "Final verification", automated end to end against the seeded
 * data (real categories, governorates and static pages). It lives in the Search suite because step 4
 * uses the FULLTEXT index, which only sees committed rows (DatabaseTruncation).
 */

it('walks the whole platform: register, post, moderate, search, contact, report, expire, renew, SEO', function () {
    Storage::fake('public');
    $sms = fakeSms();
    Filament::setCurrentPanel('admin');

    $this->seed(DatabaseSeeder::class);
    $moderator = User::factory()->moderator()->create(['name' => 'المشرف']);
    $cairo = Governorate::where('slug', 'cairo')->sole();
    $nasrCity = City::where('governorate_id', $cairo->id)->where('slug', 'nasr-city')->sole();
    $carsForSale = Category::where('slug', 'cars-for-sale')->sole();

    // ------------------------------------------------ 1. register with a phone number, read the OTP
    $this->post('/register', [
        'name' => 'أحمد المعلن',
        'phone' => '01012345678',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        config('classifieds.honeypot_field') => '',
    ])->assertRedirect(route('phone.verification.notice'));

    $this->assertGuest();
    $this->post('/verify-phone', ['code' => $sms->lastCode()])->assertRedirect(route('dashboard'));
    $this->assertAuthenticated();

    $owner = User::where('phone', '+201012345678')->sole();

    // ------------------------------- 2. create a car listing with the required fields and 3 images
    $this->post('/ads', [
        'category_id' => $carsForSale->id,
        'governorate_id' => $cairo->id,
        'city_id' => $nasrCity->id,
        'title' => 'تويوتا كورولا 2020 بحالة ممتازة',
        'description' => 'سيارة تويوتا كورولا موديل 2020 بحالة ممتازة جداً، صيانة دورية بالتوكيل وبدون أي حوادث.',
        'price_type' => 'negotiable',
        'price' => '450000',
        'phone' => '01012345678',
        'fields' => ['brand' => 'تويوتا', 'model' => 'كورولا', 'year' => '2020', 'mileage' => '55000', 'transmission' => 'أوتوماتيك'],
        'images' => [Fixtures::image('1.jpg', 900, 600), Fixtures::image('2.jpg', 900, 600), Fixtures::image('3.png', 900, 600)],
        'cover' => 'new:1',
    ])->assertSessionHasNoErrors()->assertRedirect(route('dashboard'));

    $listing = Listing::with('media')->sole();
    expect($listing->status)->toBe(ListingStatus::Pending)
        ->and($listing->getMedia(Listing::IMAGES))->toHaveCount(3);

    // pending listings are invisible to visitors, visible to their owner with a banner
    auth()->logout();
    $this->get($listing->url())->assertNotFound();
    $this->get('/')->assertDontSee('تويوتا كورولا 2020');
    $this->actingAs($owner)->get($listing->url())->assertOk()->assertSee(__('app.listing_page.banner.pending'));
    auth()->logout();

    // ------------------------ 3. the moderator approves it: it is public and the owner is notified
    $this->actingAs($moderator);
    Livewire::test(ListListings::class)->callAction(TestAction::make('approve')->table($listing));

    auth()->logout();

    // The home sections are cached for 5 minutes (by design) and were cached empty a moment ago.
    Cache::forget('home.latest');
    Cache::forget('home.featured');

    $this->get('/')->assertSee('تويوتا كورولا 2020');
    $this->get('/category/cars')->assertSee('تويوتا كورولا 2020');
    $this->get('/category/cars-for-sale/cairo')->assertSee('تويوتا كورولا 2020');

    $this->actingAs($owner)->get('/')->assertSee('data-testid="unread-badge"', false);
    $this->get('/notifications')->assertSee('أصبح ظاهراً للزوار');
    auth()->logout();

    // ------------------- 4. search with a variant spelling (ه vs ة), then filters narrow the results
    $this->get('/search?q='.urlencode('بحاله ممتازه'))->assertOk()->assertSee('تويوتا كورولا 2020');
    $this->get('/search?q='.urlencode('كورولا').'&category=cars&price_max=500000')->assertSee('تويوتا كورولا 2020');
    $this->get('/search?q='.urlencode('كورولا').'&price_max=100000')->assertDontSee('تويوتا كورولا 2020');
    $this->get('/category/cars-for-sale?'.http_build_query(['f' => ['year' => ['min' => 2019, 'max' => 2021], 'brand' => 'تويوتا']]))
        ->assertSee('تويوتا كورولا 2020');
    $this->get('/category/cars-for-sale?'.http_build_query(['f' => ['year' => ['min' => 2022]]]))
        ->assertDontSee('تويوتا كورولا 2020');

    // ------------- 5. a guest never finds the number in the source; "إظهار الرقم" reveals it + WhatsApp
    $html = $this->get($listing->url())->assertOk()->getContent();
    expect($html)->not->toContain('1012345678')->and($html)->not->toContain('wa.me')
        ->and($html)->toContain(__('app.listing_page.show_phone'));

    $contact = $this->postJson("/ad/{$listing->id}/contact")->assertOk()->assertJsonPath('phone', '+201012345678');
    expect($contact->json('whatsapp_url'))->toStartWith('https://wa.me/201012345678?text=');

    // ------------------------ 6. another user reports it; the report reaches Filament, then dismissed
    $reporter = User::factory()->create();
    $this->actingAs($reporter)->post("/ad/{$listing->id}/report", ['reason' => 'scam', 'note' => 'طلب تحويلاً مسبقاً'])
        ->assertSessionHas('success');

    $this->actingAs($moderator);
    $report = Report::sole();
    Livewire::test(ListReports::class)
        ->assertCanSeeTableRecords([$report])
        ->callAction(TestAction::make('dismiss')->table($report));

    expect($report->fresh()->status)->toBe(ReportStatus::Dismissed);
    auth()->logout();

    // ----- 7. expiry: past expires_at -> listings:expire -> 410, "expired" tab for the owner, renew
    $listing->update(['expires_at' => now()->subDay()]);
    $this->artisan('listings:expire')->assertSuccessful();

    $this->get($listing->url())->assertStatus(410);
    $this->actingAs($owner)->get('/dashboard?status=expired')->assertSee('تويوتا كورولا 2020')->assertSee(route('listings.renew', $listing), false);
    $this->post("/ads/{$listing->id}/renew")->assertSessionHas('success');
    auth()->logout();
    $this->get($listing->url())->assertOk();

    // ------- 9. SEO: canonical, Open Graph, JSON-LD; the sitemap lists it after `sitemap:generate`
    $page = $this->get($listing->url())->getContent();
    expect($page)->toContain('<link rel="canonical" href="'.$listing->url().'">')
        ->and($page)->toContain('<meta property="og:title" content="تويوتا كورولا 2020 بحالة ممتازة">')
        ->and($page)->toContain('application/ld+json')
        ->and($page)->toContain('"@type":"Product"');

    $publicDir = sys_get_temp_dir().DIRECTORY_SEPARATOR.'shams-e2e-'.bin2hex(random_bytes(4));
    File::ensureDirectoryExists($publicDir);
    File::copy(base_path('public/robots.txt'), $publicDir.'/robots.txt');
    app()->usePublicPath($publicDir);

    $this->artisan('sitemap:generate')->assertSuccessful();
    expect(file_get_contents($publicDir.'/sitemap.xml'))->toContain(htmlspecialchars($listing->url(), ENT_XML1))
        ->and(file_get_contents($publicDir.'/sitemap.xml'))->toContain(route('categories.governorate', ['cars-for-sale', 'cairo']));

    File::deleteDirectory($publicDir);
});
