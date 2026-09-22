<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\SyncListingFieldValues;
use App\Enums\FieldType;
use App\Enums\ListingStatus;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\Favorite;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\Report;
use App\Models\User;
use App\Services\ArabicText;
use App\Services\ListingSearchText;
use Illuminate\Database\Seeder;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\File;
use Spatie\Permission\Models\Role;

/**
 * Local demo data: ~200 listings across every category with realistic dynamic field values and
 * generated placeholder images, demo users, a moderator, favorites and sample reports.
 *
 * Only runs in the "local" (or "testing") environment: `php artisan db:seed --class=DemoSeeder`.
 * Image conversions are queued, so run `php artisan queue:work` to generate the WebP versions
 * (until then the original placeholder is served).
 */
class DemoSeeder extends Seeder
{
    /** Number of listings to create (tests lower it). */
    public static int $listingCount = 200;

    private const DEMO_PASSWORD = 'password';

    private const MODERATOR_PHONE = '01111111111';

    /** leaf slug => [price range, title pool]. Categories not listed use the fallback below. */
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

        // The demo data needs the reference data (idempotent seeders).
        $this->call([RoleSeeder::class, GeographySeeder::class, CategorySeeder::class, PageSeeder::class]);

        if (User::where('phone', $this->demoPhone(1))->exists()) {
            $this->command?->info('Demo data already exists. Nothing was created.');

            return;
        }

        $users = $this->createUsers();
        $this->createModerator();
        $imageFiles = $this->createPlaceholderImages();

        $listings = $this->createListings($users, $imageFiles);
        $this->createFavoritesAndReports($users, $listings);

        File::deleteDirectory(storage_path('app/demo-placeholders'));

        $this->command?->info(count($listings).' demo listings created. Run `php artisan queue:work` to generate the image conversions.');
    }

    // ------------------------------------------------------------------ users

    /**
     * @return Collection<int, User>
     */
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

    // ----------------------------------------------------------------- images

    /**
     * Generates a handful of colourful 800x600 JPEGs (gradient plus a few shapes) with GD.
     *
     * @return list<string> absolute file paths
     */
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

    // --------------------------------------------------------------- listings

    /**
     * @param  Collection<int, User>  $users
     * @param  list<string>  $imageFiles
     * @return Collection<int, Listing>
     */
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

    /**
     * @param  array{0: int, 1: int}  $range
     */
    private function randomPriceType(array $range): PriceType
    {
        if ($range[1] === 0) {
            return PriceType::Contact; // jobs: no price
        }

        return match (true) {
            random_int(1, 100) <= 60 => PriceType::Fixed,
            random_int(1, 100) <= 70 => PriceType::Negotiable,
            random_int(1, 100) <= 60 => PriceType::Contact,
            default => PriceType::Free,
        };
    }

    /**
     * @param  array{0: int, 1: int}  $range
     */
    private function randomPrice(array $range): int
    {
        $price = random_int($range[0], $range[1]);

        // Round to a "human" number: 50 / 500 / 5,000 depending on magnitude.
        $step = $price >= 100_000 ? 5_000 : ($price >= 5_000 ? 500 : 50);

        return max($range[0], (int) (round($price / $step) * $step));
    }

    /**
     * @return array<string, Carbon|null>
     */
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

    // ------------------------------------------------------------ field values

    /**
     * @param  Collection<int, CategoryField>  $fields
     * @return array<string, string>
     */
    private function fieldValues(Collection $fields, string $governorateName): array
    {
        $values = [];

        foreach ($fields as $field) {
            // Required fields are always filled, optional ones most of the time.
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

    // --------------------------------------------------- favorites and reports

    /**
     * @param  Collection<int, User>  $users
     * @param  Collection<int, Listing>  $listings
     */
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
}
