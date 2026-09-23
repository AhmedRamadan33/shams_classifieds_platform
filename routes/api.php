<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\ConversationController;
use App\Http\Controllers\Api\V1\FavoriteController;
use App\Http\Controllers\Api\V1\GeographyController;
use App\Http\Controllers\Api\V1\ListingController;
use App\Http\Controllers\Api\V1\MyListingController;
use App\Http\Controllers\Api\V1\NotificationController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SavedSearchController;
use App\Http\Controllers\ListingContactController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function () {
    Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:otp')->name('auth.register');
    Route::post('/auth/verify-otp', [AuthController::class, 'verifyOtp'])->middleware('throttle:otp')->name('auth.verify-otp');
    Route::post('/auth/resend-otp', [AuthController::class, 'resendOtp'])->middleware('throttle:otp')->name('auth.resend-otp');
    Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('auth.login');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/auth/logout', [AuthController::class, 'logout'])->name('auth.logout');
        Route::get('/auth/me', [AuthController::class, 'me'])->name('auth.me');
    });

    Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
    Route::get('/categories/{category:slug}/fields', [CategoryController::class, 'fields'])->name('categories.fields');
    Route::get('/governorates', [GeographyController::class, 'index'])->name('governorates.index');

    Route::get('/listings', [ListingController::class, 'index'])->name('listings.index');
    Route::get('/listings/{listing}', [ListingController::class, 'show'])->name('listings.show');

    Route::post('/listings/{listing}/contact', ListingContactController::class)
        ->middleware('throttle:phone-reveal')
        ->name('listings.contact');

    Route::middleware(['auth:sanctum', 'api.not-banned'])->group(function () {
        Route::get('/my/listings', [MyListingController::class, 'index'])->name('my.listings.index');
        Route::post('/my/listings', [MyListingController::class, 'store'])
            ->middleware(['honeypot', 'throttle:listings-create'])
            ->name('my.listings.store');
        Route::get('/my/listings/{listing}', [MyListingController::class, 'show'])->name('my.listings.show');
        Route::post('/my/listings/{listing}/update', [MyListingController::class, 'update'])->name('my.listings.update');
        Route::delete('/my/listings/{listing}', [MyListingController::class, 'destroy'])->name('my.listings.destroy');
        Route::post('/my/listings/{listing}/renew', [MyListingController::class, 'renew'])->name('my.listings.renew');
        Route::post('/my/listings/{listing}/sold', [MyListingController::class, 'sold'])->name('my.listings.sold');

        Route::get('/my/favorites', [FavoriteController::class, 'index'])->name('my.favorites.index');
        Route::post('/listings/{listing}/favorite', [FavoriteController::class, 'toggle'])->name('listings.favorite');

        Route::post('/listings/{listing}/reports', [ReportController::class, 'store'])
            ->middleware('throttle:reports')
            ->name('listings.reports.store');

        Route::get('/my/notifications', [NotificationController::class, 'index'])->name('my.notifications.index');
        Route::post('/my/notifications/read-all', [NotificationController::class, 'readAll'])->name('my.notifications.read-all');

        Route::get('/my/messages', [ConversationController::class, 'index'])->name('my.messages.index');
        Route::post('/listings/{listing}/messages', [ConversationController::class, 'start'])
            ->middleware('throttle:messages')
            ->name('listings.messages.start');
        Route::get('/my/messages/{conversation}', [ConversationController::class, 'show'])->name('my.messages.show');
        Route::post('/my/messages/{conversation}', [ConversationController::class, 'store'])
            ->middleware('throttle:messages')
            ->name('my.messages.store');

        Route::get('/my/saved-searches', [SavedSearchController::class, 'index'])->name('my.saved-searches.index');
        Route::post('/my/saved-searches', [SavedSearchController::class, 'store'])->name('my.saved-searches.store');
        Route::patch('/my/saved-searches/{savedSearch}', [SavedSearchController::class, 'update'])->name('my.saved-searches.update');
        Route::delete('/my/saved-searches/{savedSearch}', [SavedSearchController::class, 'destroy'])->name('my.saved-searches.destroy');
    });
});
