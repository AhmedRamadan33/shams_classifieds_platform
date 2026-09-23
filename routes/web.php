<?php

declare(strict_types=1);

use App\Http\Controllers\AdBannerClickController;
use App\Http\Controllers\AdBannerController;
use App\Http\Controllers\AdBannerPurchaseController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryFieldsController;
use App\Http\Controllers\ConversationController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FavoriteController;
use App\Http\Controllers\FeaturedPurchaseController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\ListingContactController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\PaymobWebhookController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SavedSearchController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SellerController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\SubscriptionController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');

Route::get('/category/{category:slug}/{governorate:slug}', [CategoryController::class, 'show'])
    ->withoutScopedBindings()
    ->name('categories.governorate');
Route::get('/category/{category:slug}', [CategoryController::class, 'show'])->name('categories.show');

Route::get('/search', SearchController::class)->name('search');

Route::get('/seller/{user}', SellerController::class)->name('sellers.show');

Route::get('/store/{store:slug}', [StoreController::class, 'show'])->name('stores.show');

Route::get('/p/{page:slug}', [PageController::class, 'show'])->name('pages.show');

Route::get('/ad/{listing}/{slug?}', [ListingController::class, 'show'])->name('listings.show');

Route::post('/ad/{listing}/contact', ListingContactController::class)
    ->middleware('throttle:phone-reveal')
    ->name('listings.contact');

Route::get('/api/categories/{category:id}/fields', [CategoryFieldsController::class, 'show'])
    ->middleware('throttle:60,1')
    ->name('categories.fields');

Route::post('/payments/webhook/paymob', PaymobWebhookController::class)->name('payments.webhook.paymob');

Route::get('/payments/return', [FeaturedPurchaseController::class, 'returnFromGateway'])->name('payments.return-from-gateway');

Route::get('/banner-click/{adBanner}', AdBannerClickController::class)->name('ad-banners.click');

Route::middleware(['auth', 'phone.verified'])->group(function () {
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/favorites', [FavoriteController::class, 'index'])->name('favorites.index');
    Route::post('/ad/{listing}/favorite', [FavoriteController::class, 'toggle'])->name('listings.favorite');

    Route::post('/ad/{listing}/report', [ReportController::class, 'store'])
        ->middleware('throttle:reports')
        ->name('listings.report');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'readAll'])->name('notifications.read-all');

    Route::get('/ads/create', [ListingController::class, 'create'])->name('listings.create');
    Route::post('/ads', [ListingController::class, 'store'])
        ->middleware(['honeypot', 'throttle:listings-create'])
        ->name('listings.store');
    Route::get('/ads/{listing}/edit', [ListingController::class, 'edit'])->name('listings.edit');
    Route::put('/ads/{listing}', [ListingController::class, 'update'])->name('listings.update');
    Route::delete('/ads/{listing}', [ListingController::class, 'destroy'])->name('listings.destroy');
    Route::post('/ads/{listing}/renew', [ListingController::class, 'renew'])->name('listings.renew');
    Route::post('/ads/{listing}/sold', [ListingController::class, 'sold'])->name('listings.sold');

    Route::get('/ads/{listing}/feature', [FeaturedPurchaseController::class, 'create'])->name('listings.feature');
    Route::post('/ads/{listing}/feature', [FeaturedPurchaseController::class, 'store'])->name('listings.feature.store');
    Route::get('/payments/{payment}', [FeaturedPurchaseController::class, 'show'])->name('payments.show');

    Route::get('/messages', [ConversationController::class, 'index'])->name('messages.index');
    Route::post('/ad/{listing}/message', [ConversationController::class, 'start'])
        ->middleware('throttle:messages')
        ->name('listings.message');
    Route::get('/messages/{conversation}', [ConversationController::class, 'show'])->name('messages.show');
    Route::post('/messages/{conversation}', [ConversationController::class, 'store'])
        ->middleware('throttle:messages')
        ->name('messages.store');
    Route::get('/messages/{conversation}/poll', [ConversationController::class, 'poll'])
        ->middleware('throttle:60,1')
        ->name('messages.poll');

    Route::get('/saved-searches', [SavedSearchController::class, 'index'])->name('saved-searches.index');
    Route::post('/saved-searches', [SavedSearchController::class, 'store'])->name('saved-searches.store');
    Route::patch('/saved-searches/{savedSearch}', [SavedSearchController::class, 'update'])->name('saved-searches.update');
    Route::delete('/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('saved-searches.destroy');

    Route::get('/store', [StoreController::class, 'edit'])->name('store.edit');
    Route::post('/store', [StoreController::class, 'save'])->name('store.save');

    Route::get('/subscribe', [SubscriptionController::class, 'create'])->name('subscribe');
    Route::post('/subscribe', [SubscriptionController::class, 'store'])->name('subscribe.store');

    Route::get('/advertise', [AdBannerController::class, 'create'])->name('ad-banners.create');
    Route::post('/advertise', [AdBannerController::class, 'store'])
        ->middleware(['honeypot', 'throttle:ad-banners-create'])
        ->name('ad-banners.store');
    Route::get('/banners', [AdBannerController::class, 'index'])->name('ad-banners.index');
    Route::get('/banners/{adBanner}/purchase', [AdBannerPurchaseController::class, 'create'])->name('ad-banners.purchase');
    Route::post('/banners/{adBanner}/purchase', [AdBannerPurchaseController::class, 'store'])->name('ad-banners.purchase.store');

    Route::post('/seller/{seller}/reviews', [ReviewController::class, 'store'])
        ->middleware('throttle:reviews')
        ->name('reviews.store');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
});

require __DIR__.'/auth.php';
