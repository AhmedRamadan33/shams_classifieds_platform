<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Models\AdBanner;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Listing;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Report;
use App\Models\Review;
use App\Models\SavedSearch;
use App\Models\Store;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\AdBannerApproved;
use App\Notifications\ListingApproved;
use App\Notifications\ListingExpiringSoon;
use App\Notifications\ListingRejected;
use App\Notifications\SavedSearchMatched;
use Database\Seeders\Support\DemoConversations;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ShowcaseAccountsSeeder extends Seeder
{
    private const USER_EMAIL = 'user1@shams.test';

    private const MARKER_SEARCH = 'لابتوب حتى 20 ألف';

    private const REVIEW_COMMENTS = [
        'تعامل راقي جداً والسلعة كانت مطابقة للوصف تماماً، أنصح بالتعامل معه.',
        'رد سريع والتزام بالمواعيد، وأمانة في كل حاجة.',
        'اشتريت منه أكتر من مرة وكل مرة نفس الجودة والاحترام.',
        'كان في تفاوض بسيط على السعر وانتهى بشكل ودي، شكراً على التعامل.',
        'السلعة في حالة أحسن مما توقعت، وتسليم في نفس اليوم.',
        'بائع محترم، بس الرد على الرسايل كان بيتأخر شوية.',
    ];

    public function run(): void
    {
        $user = User::query()->where('email', self::USER_EMAIL)->first();

        if ($user === null) {
            return;
        }

        $admin = User::query()->whereHas('roles', fn (Builder $query) => $query->where('name', User::ROLE_ADMIN))->orderBy('id')->first();

        $alreadyRan = SavedSearch::query()->where('user_id', $user->id)->where('name', self::MARKER_SEARCH)->exists();

        if ($alreadyRan) {
            $this->command?->info('Showcase accounts already have their data. Nothing was created.');

            return;
        }

        $others = User::query()->where('phone', 'like', '+2012%')->whereKeyNot($user->id)->get();

        $this->completeListings($user);

        if ($admin !== null) {
            $this->handListingsTo($admin, 5);
        }

        $accounts = collect([$user, $admin])->filter();

        foreach ($accounts as $account) {
            $this->addFavorites($account, $account->is($user) ? 12 : 6);
            $this->addStore($account);
            $this->addConversations($account, $others);
            $this->addReviews($account, $others);
        }

        $this->addSavedSearches($user, $admin);
        $this->addReports($user);
        $this->addNotifications($user);

        foreach ($accounts as $account) {
            $account->update(['notify_email' => true]);
        }
    }

    private function completeListings(User $user): void
    {
        $bannerTargets = AdBanner::query()->whereNotNull('listing_id')->pluck('listing_id');

        $pool = fn () => Listing::query()
            ->where('user_id', $user->id)
            ->where('status', ListingStatus::Active->value)
            ->whereNull('featured_until')
            ->whereNotIn('id', $bannerTargets)
            ->orderBy('id')
            ->get();

        $wanted = [
            ListingStatus::Pending->value => ['published_at' => null, 'expires_at' => null],
            ListingStatus::Rejected->value => ['published_at' => null, 'expires_at' => null, 'rejection_reason' => 'الصور غير واضحة أو لا تُظهر السلعة. أضف صوراً أوضح وأعد المحاولة.'],
            ListingStatus::Expired->value => ['published_at' => now()->subDays(48), 'expires_at' => now()->subDays(18)],
            ListingStatus::Sold->value => [],
        ];

        foreach ($wanted as $status => $attributes) {
            $candidates = $pool();

            if ($candidates->count() < 4 || Listing::query()->where('user_id', $user->id)->where('status', $status)->exists()) {
                continue;
            }

            $candidates->first()->forceFill(['status' => ListingStatus::from($status)] + $attributes)->save();
        }

        $candidates = $pool();

        if ($candidates->count() >= 3) {
            $candidates->first()->forceFill(['expires_at' => now()->addDays(2)])->save();
        }

        $featured = Listing::query()->where('user_id', $user->id)->where('status', ListingStatus::Active->value)->whereNotNull('featured_until')->count();
        $package = Package::query()->active()->orderBy('id')->first();

        foreach ($pool()->skip(1)->take(max(0, 2 - $featured)) as $listing) {
            $listing->forceFill(['featured_until' => now()->addDays(random_int(5, 12))])->save();

            if ($package !== null) {
                Payment::firstOrCreate(
                    ['listing_id' => $listing->id, 'package_id' => $package->id],
                    [
                        'user_id' => $user->id,
                        'gateway' => 'fake',
                        'amount' => $package->price,
                        'currency' => config('classifieds.currency_code'),
                        'status' => PaymentStatus::Paid,
                        'gateway_order_id' => 'DEMO-'.Str::upper(Str::random(10)),
                        'gateway_transaction_id' => 'DEMO-TXN-'.$listing->id,
                        'paid_at' => now()->subDays(random_int(0, 3)),
                    ],
                );
            }
        }
    }

    private function handListingsTo(User $admin, int $count): void
    {
        $bannerTargets = AdBanner::query()->whereNotNull('listing_id')->pluck('listing_id');

        Listing::query()
            ->where('status', ListingStatus::Active->value)
            ->whereNull('featured_until')
            ->whereNotIn('id', $bannerTargets)
            ->whereHas('user', fn (Builder $query) => $query->where('phone', 'like', '+2012%')->where('email', '!=', self::USER_EMAIL))
            ->whereNotIn('id', Conversation::query()->select('listing_id'))
            ->inRandomOrder()
            ->limit($count)
            ->get()
            ->each(fn (Listing $listing) => $listing->forceFill(['user_id' => $admin->id])->save());
    }

    private function addFavorites(User $account, int $count): void
    {
        Listing::query()
            ->where('status', ListingStatus::Active->value)
            ->where('user_id', '!=', $account->id)
            ->whereDoesntHave('favorites', fn (Builder $query) => $query->where('user_id', $account->id))
            ->inRandomOrder()
            ->limit($count)
            ->get()
            ->each(fn (Listing $listing) => Favorite::firstOrCreate(['user_id' => $account->id, 'listing_id' => $listing->id]));
    }

    private function addStore(User $account): void
    {
        if (Store::query()->where('user_id', $account->id)->exists()) {
            return;
        }

        $isAdmin = $account->isAdmin();

        Store::create([
            'user_id' => $account->id,
            'name' => $isAdmin ? 'متجر شمس الرسمي' : $account->name.' للتجارة العامة',
            'slug' => $isAdmin ? 'shams-official' : 'store-'.$account->id,
            'bio' => $isAdmin
                ? 'المتجر الرسمي لموقع شمس: عروض مختارة بعناية من فريق الإدارة، مع ضمان جودة الإعلان وسرعة الرد.'
                : 'متجر موثوق يقدم تشكيلة متنوعة من المنتجات بأسعار مناسبة، مع رد سريع وتفاوض مرن.',
        ]);

        $plan = Plan::query()->active()->orderBy('id')->first();

        if ($plan === null) {
            return;
        }

        $subscription = Subscription::create([
            'user_id' => $account->id,
            'plan_id' => $plan->id,
            'status' => SubscriptionStatus::Active,
            'starts_at' => now()->subDays(8),
            'expires_at' => now()->addDays(max(1, $plan->duration_days - 8)),
        ]);

        Payment::create([
            'user_id' => $account->id,
            'subscription_id' => $subscription->id,
            'gateway' => 'fake',
            'amount' => $plan->price,
            'currency' => config('classifieds.currency_code'),
            'status' => PaymentStatus::Paid,
            'gateway_order_id' => 'DEMO-'.Str::upper(Str::random(10)),
            'gateway_transaction_id' => 'DEMO-TXN-SUB-'.$subscription->id,
            'paid_at' => now()->subDays(8),
        ]);
    }

    private function addConversations(User $account, Collection $others): void
    {
        $isMainUser = $account->email === self::USER_EMAIL;

        $bought = Listing::query()
            ->with(['user', 'category'])
            ->where('status', ListingStatus::Active->value)
            ->where('user_id', '!=', $account->id)
            ->whereNotIn('id', Conversation::query()->select('listing_id'))
            ->inRandomOrder()
            ->limit($isMainUser ? 4 : 2)
            ->get();

        foreach ($bought as $index => $listing) {
            DemoConversations::create($listing, $account, unreadCount: $index < 2 ? 1 : 0, daysAgo: $index + 1);
        }

        $sold = Listing::query()
            ->with(['user', 'category'])
            ->where('status', ListingStatus::Active->value)
            ->where('user_id', $account->id)
            ->whereNotIn('id', Conversation::query()->select('listing_id'))
            ->inRandomOrder()
            ->limit($isMainUser ? 4 : 2)
            ->get();

        foreach ($sold as $index => $listing) {
            $buyer = $others->where('id', '!=', $account->id)->shuffle()->first();

            if ($buyer !== null) {
                DemoConversations::create($listing, $buyer, unreadCount: $index < 2 ? 1 : 0, daysAgo: $index + 1, endsWithBuyer: true);
            }
        }
    }

    private function addReviews(User $account, Collection $others): void
    {
        $received = $others->shuffle()->take($account->email === self::USER_EMAIL ? 5 : 2)->values();
        $ratings = [5, 5, 4, 5, 3];

        foreach ($received as $index => $reviewer) {
            Review::firstOrCreate(
                ['reviewer_id' => $reviewer->id, 'seller_id' => $account->id],
                ['rating' => $ratings[$index], 'comment' => self::REVIEW_COMMENTS[$index % count(self::REVIEW_COMMENTS)]],
            );
        }

        if ($account->email !== self::USER_EMAIL) {
            return;
        }

        foreach ($others->shuffle()->take(3)->values() as $index => $seller) {
            Review::firstOrCreate(
                ['reviewer_id' => $account->id, 'seller_id' => $seller->id],
                ['rating' => [5, 4, 5][$index], 'comment' => self::REVIEW_COMMENTS[($index + 2) % count(self::REVIEW_COMMENTS)]],
            );
        }
    }

    private function addSavedSearches(User $user, ?User $admin): void
    {
        $searches = [
            [$user, self::MARKER_SEARCH, ['category_slug' => 'computers-and-laptops', 'filters' => ['price_max' => 20000], 'notify' => true]],
            [$user, 'موبايلات في القاهرة', ['category_slug' => 'mobiles', 'governorate_slug' => 'cairo', 'filters' => [], 'notify' => true]],
            [$admin, 'سيارات للبيع', ['category_slug' => 'cars-for-sale', 'filters' => [], 'notify' => false]],
        ];

        foreach ($searches as [$owner, $name, $attributes]) {
            if ($owner !== null) {
                SavedSearch::firstOrCreate(['user_id' => $owner->id, 'name' => $name], $attributes);
            }
        }
    }

    private function addReports(User $user): void
    {
        if (Report::query()->where('user_id', $user->id)->exists()) {
            return;
        }

        $listings = Listing::query()
            ->where('status', ListingStatus::Active->value)
            ->where('user_id', '!=', $user->id)
            ->whereDoesntHave('reports', fn (Builder $query) => $query->where('user_id', $user->id))
            ->inRandomOrder()
            ->limit(2)
            ->get();

        $moderator = User::query()->where('email', 'moderator@shams.test')->first();

        foreach ($listings as $index => $listing) {
            Report::create([
                'listing_id' => $listing->id,
                'user_id' => $user->id,
                'reason' => $index === 0 ? 'duplicate' : 'other',
                'note' => $index === 0 ? null : 'الصور لا تطابق الوصف المكتوب في الإعلان.',
                'status' => $index === 0 ? 'resolved' : 'open',
                'handled_by' => $index === 0 ? $moderator?->id : null,
            ]);
        }
    }

    private function addNotifications(User $user): void
    {
        $mine = fn (ListingStatus $status) => Listing::query()->where('user_id', $user->id)->where('status', $status->value)->orderBy('id')->first();

        $entries = [];

        if ($listing = $mine(ListingStatus::Active)) {
            $entries[] = [new ListingApproved($listing), 60 * 30, true];
        }

        if ($listing = $mine(ListingStatus::Rejected)) {
            $entries[] = [new ListingRejected($listing, (string) $listing->rejection_reason), 60 * 5, false];
        }

        $expiring = Listing::query()->where('user_id', $user->id)->where('status', ListingStatus::Active->value)->whereBetween('expires_at', [now(), now()->addDays(3)])->first();

        if ($expiring) {
            $entries[] = [new ListingExpiringSoon($expiring), 60 * 3, false];
        }

        if ($search = SavedSearch::query()->where('user_id', $user->id)->where('notify', true)->first()) {
            $entries[] = [new SavedSearchMatched($search, 3), 60 * 9, false];
        }

        if ($banner = AdBanner::query()->where('user_id', $user->id)->where('status', 'active')->first()) {
            $entries[] = [new AdBannerApproved($banner), 60 * 50, true];
        }

        foreach ($entries as [$notification, $minutesAgo, $read]) {
            $user->notifyNow($notification);

            $latest = DB::table('notifications')->where('notifiable_id', $user->id)->where('notifiable_type', $user->getMorphClass())->where('type', $notification::class)->orderByDesc('created_at')->first();
            $at = now()->subMinutes($minutesAgo);

            DB::table('notifications')->where('id', $latest->id)->update(['created_at' => $at, 'updated_at' => $at, 'read_at' => $read ? $at->copy()->addMinutes(10) : null]);
        }
    }
}
