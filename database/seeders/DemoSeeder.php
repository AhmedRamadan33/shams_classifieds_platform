<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SyncListingFieldValues;
use App\Enums\AdBannerStatus;
use App\Enums\FieldType;
use App\Enums\ListingStatus;
use App\Enums\PaymentStatus;
use App\Enums\PriceType;
use App\Enums\SubscriptionStatus;
use App\Models\AdBanner;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Conversation;
use App\Models\Favorite;
use App\Models\Governorate;
use App\Models\HeroSlide;
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
use App\Services\ArabicText;
use App\Services\ListingSearchText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public static int $listingCount = 200;

    private const DEMO_PASSWORD = 'password';

    private const MODERATOR_PHONE = '01111111111';

    private const CATALOG = [
        'cars-for-sale' => [[90_000, 1_800_000], ['تويوتا كورولا فبريكا بالكامل', 'هيونداي إلنترا حالة ممتازة', 'كيا سبورتاج موديل حديث', 'نيسان صني بحالة الزيرو', 'شيفروليه أوبترا ماشية قليل', 'مرسيدس C180 فل الفل']],
        'cars-for-rent' => [[600, 4_000], ['سيارة للإيجار اليومي مع سائق', 'إيجار سيارات لحفلات الزفاف', 'تأجير ميكروباص رحلات', 'سيارة اقتصادية للإيجار الشهري']],
        'car-parts' => [[150, 25_000], ['كاوتش جديد مقاس 16', 'بطارية سيارة أصلية', 'جنوط ألومنيوم مستعملة', 'قطع غيار تويوتا أصلية', 'شاشة أندرويد للسيارة']],
        'motorcycles' => [[12_000, 180_000], ['موتوسيكل هوندا 2020 حالة ممتازة', 'سكوتر ياماها اقتصادي', 'دراجة نارية بجاج رخصة سارية']],
        'apartments-for-sale' => [[450_000, 6_500_000], ['شقة للبيع بمدينة نصر تشطيب سوبر لوكس', 'شقة 150 متر بالتجمع الخامس', 'شقة بالمعادي قريبة من المترو', 'شقة تمليك بالهرم ناصية', 'شقة بمدينتي جاهزة للسكن']],
        'apartments-for-rent' => [[2_500, 35_000], ['شقة للإيجار بالمهندسين مفروشة', 'شقة إيجار جديد بمدينة نصر', 'شقة للإيجار بالشيخ زايد', 'استوديو للإيجار بالزمالك']],
        'villas' => [[3_500_000, 30_000_000], ['فيلا للبيع بالشيخ زايد حديقة خاصة', 'فيلا مستقلة بالتجمع الأول', 'تاون هاوس بالرحاب جاهز للاستلام']],
        'land' => [[600_000, 9_000_000], ['أرض للبيع بالعاشر من رمضان', 'قطعة أرض سكنية بأكتوبر', 'أرض زراعية بطريق مصر إسكندرية']],
        'shops-and-offices' => [[350_000, 5_000_000], ['محل للبيع بموقع تجاري متميز', 'مكتب إداري للإيجار بوسط البلد', 'محل ناصية على شارع رئيسي']],
        'job-vacancies' => [[0, 0], ['مطلوب محاسب بخبرة سنتين', 'وظيفة سائق لدى شركة', 'مطلوب مسؤول مبيعات براتب وعمولة', 'مطلوب مدرس لغة إنجليزية', 'وظائف شاغرة لمهندسين مدنيين']],
        'job-seekers' => [[0, 0], ['أبحث عن عمل في مجال المحاسبة', 'مبرمج ويب يبحث عن فرصة عمل', 'خريج تجارة يبحث عن وظيفة إدارية']],
        'mobiles' => [[1_500, 65_000], ['آيفون 13 برو بحالة الجديد', 'سامسونج جالاكسي S22 بالضمان', 'شاومي ريدمي نوت 11 كسر زيرو', 'أوبو رينو 8 استعمال خفيف']],
        'computers-and-laptops' => [[4_000, 90_000], ['لابتوب ديل كور i7 بحالة ممتازة', 'ماك بوك إير M1 نظيف', 'كمبيوتر جيمنج بكارت شاشة قوي', 'لابتوب لينوفو للطلبة']],
        'tvs-and-screens' => [[3_000, 45_000], ['شاشة سامسونج 55 بوصة سمارت', 'تلفزيون إل جي 4K بحالة الزيرو', 'شاشة كمبيوتر 27 بوصة']],
        'cameras' => [[2_500, 80_000], ['كاميرا كانون 90D مع عدسة', 'كاميرا سوني A7 III بالكرتونة', 'كاميرا مراقبة واي فاي']],
        'video-games' => [[1_000, 30_000], ['بلايستيشن 5 مع دراعين', 'إكس بوكس سيريس إس جديد', 'ألعاب بلايستيشن 4 للبيع']],
        'furniture' => [[800, 60_000], ['غرفة نوم كاملة خشب زان', 'ركنة مودرن 6 مقاعد', 'سفرة 8 كراسي بحالة ممتازة', 'مكتب خشب مع كرسي']],
        'home-appliances' => [[1_000, 40_000], ['غسالة أوتوماتيك 10 كيلو', 'ثلاجة نو فروست 18 قدم', 'بوتاجاز 5 شعلة', 'تكييف 1.5 حصان بارد ساخن']],
        'decor-and-housewares' => [[100, 8_000], ['طقم أواني جرانيت 10 قطع', 'ستائر مودرن للصالون', 'لوحات ديكور جدارية', 'سجادة تركي 3×4']],
        'maintenance-and-finishing' => [[200, 20_000], ['سباك محترف لجميع الأعمال', 'نقاش ودهانات بأسعار مناسبة', 'فني كهرباء وتأسيس', 'تركيب سيراميك وبورسلين']],
        'transport-and-shipping' => [[300, 6_000], ['نقل أثاث بسيارات مجهزة', 'خدمة شحن بضائع بين المحافظات', 'توصيل طلبات داخل القاهرة']],
        'lessons-and-courses' => [[100, 3_000], ['دروس خصوصية رياضيات للثانوية', 'كورس برمجة للمبتدئين أونلاين', 'مدرس لغة عربية للمرحلة الإعدادية']],
        'events-and-photography' => [[1_500, 25_000], ['تصوير فوتوغرافي لحفلات الزفاف', 'تنظيم حفلات أعياد ميلاد', 'تصوير فيديو احترافي للمناسبات']],
        'other-services' => [[100, 5_000], ['خدمة تنظيف منازل ومكاتب', 'ترجمة معتمدة عربي إنجليزي', 'تصميم شعارات وهويات تجارية']],
        'clothes' => [[100, 4_000], ['بدلة رجالي إيطالي جديدة', 'فستان سهرة استعمال مرة واحدة', 'جاكيت جلد طبيعي', 'ملابس أطفال ماركات']],
        'shoes-and-bags' => [[150, 6_000], ['حذاء رياضي نايك أصلي', 'شنطة يد جلد طبيعي', 'حذاء كلاسيك رجالي']],
        'watches-and-jewelry' => [[400, 90_000], ['ساعة كاسيو أصلية', 'خاتم ذهب عيار 21', 'ساعة أوميجا بحالة الجديد']],
        'other' => [[50, 20_000], ['سلعة متنوعة للبيع بحالة جيدة', 'مستلزمات متنوعة بأسعار مخفضة', 'أغراض مستعملة للبيع']],
    ];

    private const NAMES = [
        'أحمد علي', 'محمد إبراهيم', 'محمود حسن', 'مصطفى كمال', 'عمر خالد', 'يوسف سامي',
        'فاطمة محمود', 'سارة أحمد', 'مريم عادل', 'نور الدين', 'هدى مصطفى', 'إسلام رمضان',
    ];

    private const SENTENCES = [
        'الإعلان بحالة ممتازة ولم يُستخدم كثيراً.',
        'السعر قابل للتفاوض مع الجادين فقط.',
        'المعاينة متاحة في أي وقت بعد الاتصال.',
        'كل الأوراق سليمة ومتوفرة وجاهز للتسليم فوراً.',
        'للتواصل يرجى الاتصال أو مراسلتي عبر واتساب.',
        'الصور حقيقية من الموقع وبدون أي تعديل.',
        'أسباب البيع: تغيير الاحتياجات، ولا يوجد أي عيوب.',
        'الفرصة مناسبة جداً لمن يبحث عن جودة وسعر معقول.',
    ];

    private const REJECTION_REASONS = [
        'الصور غير واضحة أو لا تُظهر السلعة.',
        'الإعلان يحتوي على بيانات اتصال داخل الوصف.',
        'الوصف غير كافٍ. أضف تفاصيل أكثر وأعد المحاولة.',
    ];

    public function run(): void
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->command?->warn('DemoSeeder only runs in the local environment. Nothing was created.');

            return;
        }

        $this->call([
            RoleSeeder::class, GeographySeeder::class, CategorySeeder::class, PageSeeder::class,
            PackageSeeder::class, PlanSeeder::class, AdPackageSeeder::class,
        ]);

        if (User::where('phone', $this->demoPhone(1))->exists()) {
            $this->command?->info('Demo data already exists. Nothing was created.');

            return;
        }

        $users = $this->createUsers();
        $this->createModerator();
        $imageFiles = $this->createPlaceholderImages();

        $listings = $this->createListings($users, $imageFiles);
        $this->guaranteeFeaturedListings($listings);
        $this->createFavoritesAndReports($users, $listings);
        $this->createStoresAndSubscriptions($users);
        $this->createReviews($users);
        $this->createConversations($users, $listings);
        $this->createSavedSearches($users);
        $this->createHeroSlides($imageFiles);
        $this->createAdBanners($users, $imageFiles);

        File::deleteDirectory(storage_path('app/demo-placeholders'));

        $this->command?->info(count($listings).' demo listings created. Run `php artisan queue:work` to generate the image conversions.');
    }

    private function createUsers(): Collection
    {
        return collect(range(1, 12))->map(function (int $i) {
            $user = User::create([
                'name' => self::NAMES[$i - 1],
                'phone' => $this->demoPhone($i),
                'phone_verified_at' => now()->subDays(random_int(20, 90)),
                'password' => self::DEMO_PASSWORD,
            ]);
            $user->forceFill(['created_at' => now()->subDays(random_int(20, 200))])->save();

            return $user;
        });
    }

    private function createModerator(): void
    {
        $moderator = User::create([
            'name' => 'مشرف تجريبي',
            'phone' => '+20'.ltrim(self::MODERATOR_PHONE, '0'),
            'phone_verified_at' => now(),
            'password' => self::DEMO_PASSWORD,
        ]);

        $moderator->syncRoles(Role::findOrCreate(User::ROLE_MODERATOR, 'web'));
    }

    private function demoPhone(int $i): string
    {
        return '+2012'.str_pad((string) $i, 8, '0', STR_PAD_LEFT);
    }

    private function createPlaceholderImages(): array
    {
        $directory = storage_path('app/demo-placeholders');
        File::ensureDirectoryExists($directory);

        $palette = [
            [[251, 146, 60], [194, 65, 12]], [[96, 165, 250], [30, 64, 175]], [[52, 211, 153], [4, 120, 87]],
            [[244, 114, 182], [157, 23, 77]], [[167, 139, 250], [91, 33, 182]], [[250, 204, 21], [161, 98, 7]],
            [[148, 163, 184], [51, 65, 85]], [[248, 113, 113], [153, 27, 27]],
        ];

        $files = [];

        foreach ($palette as $i => [$from, $to]) {
            $image = imagecreatetruecolor(800, 600);

            for ($y = 0; $y < 600; $y++) {
                $ratio = $y / 599;
                $color = imagecolorallocate(
                    $image,
                    (int) ($from[0] + ($to[0] - $from[0]) * $ratio),
                    (int) ($from[1] + ($to[1] - $from[1]) * $ratio),
                    (int) ($from[2] + ($to[2] - $from[2]) * $ratio),
                );
                imageline($image, 0, $y, 799, $y, $color);
            }

            $white = imagecolorallocatealpha($image, 255, 255, 255, 90);
            imagefilledellipse($image, 250 + $i * 40, 300, 320, 320, $white);
            imagefilledrectangle($image, 420, 180, 700, 420, $white);
            imagefilledellipse($image, 560, 300, 120, 120, imagecolorallocatealpha($image, 255, 255, 255, 60));

            $path = $directory.DIRECTORY_SEPARATOR."placeholder-{$i}.jpg";
            imagejpeg($image, $path, 82);
            $files[] = $path;
        }

        return $files;
    }

    private function createListings(Collection $users, array $imageFiles): Collection
    {
        $leaves = Category::query()->whereDoesntHave('children')->get();
        $governorates = Governorate::with('cities')->get();
        $sync = app(SyncListingFieldValues::class);

        $created = collect();

        for ($n = 0; $n < static::$listingCount; $n++) {
            $category = $leaves->random();
            [$priceRange, $titles] = self::CATALOG[$category->slug] ?? self::CATALOG['other'];
            $governorate = $governorates->random();
            $city = $governorate->cities->isNotEmpty() && random_int(1, 10) > 3 ? $governorate->cities->random() : null;

            $title = Arr::random($titles);
            $description = $title.'. '.implode(' ', Arr::random(self::SENTENCES, 3));

            $status = $this->randomStatus();
            $priceType = $this->randomPriceType($priceRange);

            $attributes = [
                'user_id' => $users->random()->id,
                'category_id' => $category->id,
                'governorate_id' => $governorate->id,
                'city_id' => $city?->id,
                'title' => $title,
                'slug' => ArabicText::slug($title) ?: 'listing',
                'description' => $description,
                'price_type' => $priceType,
                'price' => $priceType->needsPrice() ? $this->randomPrice($priceRange) : null,
                'phone' => '+2010'.str_pad((string) random_int(10_000_000, 99_999_999), 8, '0', STR_PAD_LEFT),
                'status' => $status,
                'views' => $status === ListingStatus::Pending ? 0 : random_int(0, 600),
            ] + $this->datesFor($status);

            if ($status === ListingStatus::Rejected) {
                $attributes['rejection_reason'] = Arr::random(self::REJECTION_REASONS);
            }

            if ($status === ListingStatus::Active && random_int(1, 100) <= 8) {
                $attributes['featured_until'] = now()->addDays(random_int(3, 14));
            }

            $listing = Listing::create($attributes);
            $fields = $category->effectiveFields();

            $values = $sync($listing, $fields, $this->fieldValues($fields, $governorate->name));
            $listing->update(['search_text' => ListingSearchText::build($title, $description, $values)]);

            foreach (Arr::random($imageFiles, random_int(1, 3)) as $file) {
                $listing->addMedia($file)->preservingOriginal()->toMediaCollection(Listing::IMAGES);
            }

            $created->push($listing);
        }

        return $created;
    }

    private function guaranteeFeaturedListings(Collection $listings): void
    {
        $active = $listings->filter(fn (Listing $listing) => $listing->status === ListingStatus::Active)->values();
        $target = (int) max(6, ceil($active->count() * 0.1));

        foreach ($active->whereNull('featured_until')->take($target) as $listing) {
            $listing->update(['featured_until' => now()->addDays(random_int(3, 14))]);
        }

        $package = Package::query()->active()->inRandomOrder()->first();

        if ($package === null) {
            return;
        }

        foreach ($active->filter(fn (Listing $listing) => $listing->isFeatured()) as $listing) {
            Payment::firstOrCreate(
                ['listing_id' => $listing->id, 'package_id' => $package->id],
                [
                    'user_id' => $listing->user_id,
                    'gateway' => 'fake',
                    'amount' => $package->price,
                    'currency' => config('classifieds.currency_code'),
                    'status' => PaymentStatus::Paid,
                    'gateway_order_id' => 'DEMO-'.Str::upper(Str::random(10)),
                    'gateway_transaction_id' => 'DEMO-TXN-'.$listing->id,
                    'paid_at' => now()->subDays(random_int(0, 5)),
                ],
            );
        }
    }

    private function randomStatus(): ListingStatus
    {
        $roll = random_int(1, 100);

        return match (true) {
            $roll <= 84 => ListingStatus::Active,
            $roll <= 91 => ListingStatus::Pending,
            $roll <= 94 => ListingStatus::Rejected,
            $roll <= 97 => ListingStatus::Expired,
            default => ListingStatus::Sold,
        };
    }

    private function randomPriceType(array $range): PriceType
    {
        if ($range[1] === 0) {
            return PriceType::Contact;
        }

        return match (true) {
            random_int(1, 100) <= 60 => PriceType::Fixed,
            random_int(1, 100) <= 70 => PriceType::Negotiable,
            random_int(1, 100) <= 60 => PriceType::Contact,
            default => PriceType::Free,
        };
    }

    private function randomPrice(array $range): int
    {
        $price = random_int($range[0], $range[1]);

        $step = $price >= 100_000 ? 5_000 : ($price >= 5_000 ? 500 : 50);

        return max($range[0], (int) (round($price / $step) * $step));
    }

    private function datesFor(ListingStatus $status): array
    {
        $duration = (int) config('classifieds.listing_duration_days');

        return match ($status) {
            ListingStatus::Pending, ListingStatus::Rejected => ['published_at' => null, 'expires_at' => null],
            ListingStatus::Expired => [
                'published_at' => $published = now()->subDays(random_int($duration + 5, $duration + 40)),
                'expires_at' => $published->copy()->addDays($duration),
            ],
            default => [
                'published_at' => $published = now()->subDays(random_int(0, $duration - 4))->subMinutes(random_int(0, 1_400)),
                'expires_at' => $published->copy()->addDays($duration),
            ],
        };
    }

    private function fieldValues(Collection $fields, string $governorateName): array
    {
        $values = [];

        foreach ($fields as $field) {
            if (! $field->is_required && random_int(1, 100) > 75) {
                continue;
            }

            $value = $this->fieldValue($field, $governorateName);

            if ($value !== null) {
                $values[$field->key] = $value;
            }
        }

        return $values;
    }

    private function fieldValue(CategoryField $field, string $governorateName): ?string
    {
        return match ($field->type) {
            FieldType::Select => Arr::random($field->optionValues()),
            FieldType::Boolean => random_int(0, 1) === 1 ? '1' : '0',
            FieldType::Number => (string) match ($field->key) {
                'year' => random_int(2008, 2024),
                'mileage' => random_int(5, 250) * 1000,
                'area' => random_int(45, 450),
                'rooms' => random_int(1, 6),
                'bathrooms' => random_int(1, 4),
                'floor' => random_int(0, 15),
                'experience_years' => random_int(0, 15),
                default => random_int(1, 100),
            },
            FieldType::Text => match ($field->key) {
                'model' => Arr::random(['كورولا', 'إلنترا', 'سبورتاج', 'صني', 'لانسر', 'أوبترا', 'أستيرا']),
                'brand' => Arr::random(['سامسونج', 'أبل', 'سوني', 'إل جي', 'ديل', 'لينوفو', 'شاومي']),
                'service_type' => Arr::random(['صيانة عامة', 'تركيب وتشطيب', 'نقل وتوصيل', 'دروس وتدريب']),
                'service_area' => $governorateName,
                'size' => Arr::random(['S', 'M', 'L', 'XL', '40', '42', '44']),
                default => null,
            },
        };
    }

    private function createFavoritesAndReports(Collection $users, Collection $listings): void
    {
        $active = $listings->filter(fn (Listing $listing) => $listing->status === ListingStatus::Active)->values();

        foreach ($active->random(min(8, $active->count())) as $listing) {
            if ($listing->user_id !== $users->first()->id) {
                Favorite::firstOrCreate(['user_id' => $users->first()->id, 'listing_id' => $listing->id]);
            }
        }

        $reasons = ['scam', 'duplicate', 'prohibited', 'sold', 'other'];

        foreach ($active->random(min(8, $active->count())) as $i => $listing) {
            $reporter = $users->firstWhere(fn (User $user) => $user->id !== $listing->user_id);

            Report::firstOrCreate(
                ['listing_id' => $listing->id, 'user_id' => $reporter->id],
                [
                    'reason' => $reasons[$i % count($reasons)],
                    'note' => $reasons[$i % count($reasons)] === 'other' ? 'الوصف لا يطابق الصور المعروضة.' : null,
                    'status' => $i >= 6 ? ($i === 6 ? 'resolved' : 'dismissed') : 'open',
                ],
            );
        }
    }

    private function createStoresAndSubscriptions(Collection $users): void
    {
        $suffixes = ['للإلكترونيات', 'للأثاث المنزلي', 'لقطع غيار السيارات', 'للموبايلات والإكسسوارات'];
        $owners = $users->take(count($suffixes))->values();
        $plan = Plan::query()->active()->inRandomOrder()->first();

        foreach ($owners as $i => $owner) {
            $store = Store::firstOrCreate(
                ['user_id' => $owner->id],
                [
                    'name' => $owner->name.' '.$suffixes[$i],
                    'slug' => 'store-'.$owner->id,
                    'bio' => 'متجر موثوق يقدم منتجات متنوعة بأسعار مناسبة وجودة عالية، مع خدمة عملاء سريعة.',
                ],
            );

            if ($i >= 2 || $plan === null || $store->wasRecentlyCreated === false) {
                continue;
            }

            $subscription = Subscription::create([
                'user_id' => $owner->id,
                'plan_id' => $plan->id,
                'status' => SubscriptionStatus::Active,
                'starts_at' => now()->subDays(5),
                'expires_at' => now()->addDays(max(1, $plan->duration_days - 5)),
            ]);

            Payment::create([
                'user_id' => $owner->id,
                'subscription_id' => $subscription->id,
                'gateway' => 'fake',
                'amount' => $plan->price,
                'currency' => config('classifieds.currency_code'),
                'status' => PaymentStatus::Paid,
                'gateway_order_id' => 'DEMO-'.Str::upper(Str::random(10)),
                'gateway_transaction_id' => 'DEMO-TXN-SUB-'.$subscription->id,
                'paid_at' => now()->subDays(5),
            ]);
        }
    }

    private function createReviews(Collection $users): void
    {
        $comments = [
            'بائع محترم وسريع في الرد.',
            'تعامل ممتاز والسلعة مطابقة للوصف تماماً.',
            'التزام بالمواعيد وجودة عالية، أنصح بالتعامل معه.',
            'رد سريع وتفاوض مرن، تجربة جيدة.',
            'أمانة في الوصف وسرعة في التسليم.',
            'من أفضل من تعاملت معهم على الموقع.',
        ];

        $pairs = $users->crossJoin($users)
            ->filter(fn (array $pair) => $pair[0]->id !== $pair[1]->id)
            ->shuffle()
            ->take(count($comments));

        foreach ($pairs as [$reviewer, $seller]) {
            Review::firstOrCreate(
                ['reviewer_id' => $reviewer->id, 'seller_id' => $seller->id],
                ['rating' => random_int(3, 5), 'comment' => Arr::random($comments)],
            );
        }
    }

    private function createConversations(Collection $users, Collection $listings): void
    {
        $thread = [
            'هل الإعلان لسه متاح؟',
            'أيوه متاح، تحت أمرك.',
            'ممكن أعرف آخر سعر؟',
            'السعر قابل للتفاوض شوية لو الاتفاق قريب.',
            'تمام، هكلمك بكرة أأكد الاتفاق.',
        ];

        $active = $listings->filter(fn (Listing $listing) => $listing->status === ListingStatus::Active)->values();

        foreach ($active->take(2) as $listing) {
            $buyer = $users->first(fn (User $user) => $user->id !== $listing->user_id);

            if ($buyer === null) {
                continue;
            }

            $conversation = Conversation::firstOrCreate(
                ['listing_id' => $listing->id, 'buyer_id' => $buyer->id, 'seller_id' => $listing->user_id],
                ['last_message_at' => now()],
            );

            if ($conversation->messages()->exists()) {
                continue;
            }

            foreach ($thread as $i => $body) {
                $conversation->messages()->create([
                    'sender_id' => $i % 2 === 0 ? $buyer->id : $listing->user_id,
                    'body' => $body,
                    'read_at' => $i < count($thread) - 1 ? now() : null,
                ]);
            }

            $conversation->update(['last_message_at' => now()]);
        }
    }

    private function createSavedSearches(Collection $users): void
    {
        $user = $users->first();

        SavedSearch::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'شقق للبيع'],
            ['category_slug' => 'apartments-for-sale', 'filters' => [], 'notify' => true],
        );

        SavedSearch::firstOrCreate(
            ['user_id' => $user->id, 'name' => 'سيارات تويوتا'],
            ['category_slug' => 'cars-for-sale', 'filters' => ['q' => 'تويوتا'], 'notify' => false],
        );
    }

    private function createHeroSlides(array $imageFiles): void
    {
        $slides = [
            ['title' => 'اكتشف آلاف الإعلانات', 'subtitle' => 'سيارات، عقارات، إلكترونيات وأكثر في مكان واحد.', 'link_url' => url('/search')],
            ['title' => 'بيع ما لا تحتاجه في دقائق', 'subtitle' => 'أضف إعلانك مجاناً ووصله للمهتمين مباشرة.', 'link_url' => route('listings.create')],
            ['title' => 'ميّز إعلانك أو أعلن معنا', 'subtitle' => 'باقات تمييز وبانرات إعلانية لزيادة ظهورك.', 'link_url' => route('ad-banners.create')],
        ];

        foreach ($slides as $i => $slide) {
            $existing = HeroSlide::where('title', $slide['title'])->first();

            if ($existing) {
                continue;
            }

            $created = HeroSlide::create($slide + ['sort_order' => $i]);
            $created->addMedia($imageFiles[$i % count($imageFiles)])->preservingOriginal()->toMediaCollection(HeroSlide::IMAGE);
        }
    }

    private function createAdBanners(Collection $users, array $imageFiles): Collection
    {
        $advertiser = $users->first();

        $banners = [
            ['placement' => 'home_top', 'title' => 'عرض المتجر الرقمي', 'target_url' => 'https://example.com/digital-store', 'status' => AdBannerStatus::Active, 'starts_at' => now()->subDays(2), 'expires_at' => now()->addDays(5)],
            ['placement' => 'search_sidebar', 'title' => 'خصم عيادة الأسنان', 'target_url' => 'https://example.com/dental-clinic', 'status' => AdBannerStatus::Active, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(20)],
            ['placement' => 'listing_sidebar', 'title' => 'شركة نقل الأثاث', 'target_url' => 'https://example.com/movers', 'status' => AdBannerStatus::Active, 'starts_at' => now()->subDays(3), 'expires_at' => now()->addDays(10)],
            ['placement' => 'home_top', 'title' => 'مطعم جديد بالتجمع', 'target_url' => 'https://example.com/restaurant', 'status' => AdBannerStatus::Pending],
            ['placement' => 'search_sidebar', 'title' => 'إعلان صورته مخالفة', 'target_url' => 'https://example.com/rejected', 'status' => AdBannerStatus::Rejected, 'rejection_reason' => 'الصورة تحتوي على نص كبير مخالف للسياسة.'],
        ];

        $created = collect();

        foreach ($banners as $i => $banner) {
            $existing = AdBanner::where('title', $banner['title'])->first();

            if ($existing) {
                $created->push($existing);

                continue;
            }

            $record = AdBanner::create(['user_id' => $advertiser->id] + $banner);
            $record->addMedia($imageFiles[$i % count($imageFiles)])->preservingOriginal()->toMediaCollection(AdBanner::IMAGE);
            $created->push($record);
        }

        return $created;
    }
}
