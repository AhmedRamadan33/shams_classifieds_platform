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
        'cars-for-sale' => [[90_000, 1_800_000], [
            'تويوتا كورولا فبريكا بالكامل' => [4, 7],
            'هيونداي إلنترا حالة ممتازة' => [7, 4],
            'كيا سبورتاج موديل حديث' => [4],
            'نيسان صني بحالة الزيرو' => [5, 4],
            'شيفروليه أوبترا ماشية قليل' => [7, 5],
            'مرسيدس C180 فل الفل' => [1, 2],
            'سيارة كلاسيك موديل قديم بحالة ممتازة' => [3, 6, 8],
        ]],
        'cars-for-rent' => [[600, 4_000], [
            'سيارة للإيجار اليومي مع سائق' => [2, 3, 5],
            'إيجار سيارة كابريو لحفلات الزفاف' => [4],
            'سيارة اقتصادية للإيجار الشهري' => [1, 3],
        ]],
        'car-parts' => [[150, 25_000], [
            'كاوتش جديد مقاس 16' => [3],
            'جنوط ألومنيوم مستعملة' => [1, 2],
            'طقم جنوط أصلي للمرسيدس' => [2],
            'عدادات ولوحة قيادة أصلية' => [4],
            'ناقل حركة عصا مانيوال' => [5],
        ]],
        'motorcycles' => [[12_000, 180_000], [
            'موتوسيكل هوندا 2020 حالة ممتازة' => [1, 3, 5],
            'سكوتر فيسبا اقتصادي' => [6],
            'دراجة نارية هارلي رخصة سارية' => [2, 4, 5],
        ]],
        'apartments-for-sale' => [[450_000, 6_500_000], [
            'شقة للبيع بمدينة نصر تشطيب سوبر لوكس' => [],
            'شقة 150 متر بالتجمع الخامس' => [],
            'شقة بالمعادي قريبة من المترو' => [],
            'شقة تمليك بالهرم ناصية' => [],
            'شقة بمدينتي جاهزة للسكن' => [],
        ]],
        'apartments-for-rent' => [[2_500, 35_000], [
            'شقة للإيجار بالمهندسين مفروشة' => [],
            'شقة إيجار جديد بمدينة نصر' => [],
            'شقة للإيجار بالشيخ زايد' => [],
            'استوديو للإيجار بالزمالك' => [],
        ]],
        'villas' => [[3_500_000, 30_000_000], [
            'فيلا للبيع بالشيخ زايد حديقة خاصة' => [],
            'فيلا مستقلة بالتجمع الخامس' => [],
            'تاون هاوس بالرحاب جاهز للاستلام' => [],
        ]],
        'land' => [[600_000, 9_000_000], [
            'أرض للبيع بالعاشر من رمضان' => [],
            'قطعة أرض سكنية بأكتوبر' => [],
            'أرض زراعية بطريق مصر إسكندرية' => [],
        ]],
        'shops-and-offices' => [[350_000, 5_000_000], [
            'محل للبيع بموقع تجاري متميز' => [1, 2, 3],
            'مكتب إداري للإيجار بوسط البلد' => [4, 5, 6],
            'محل ناصية على شارع رئيسي' => [1, 3],
        ]],
        'job-vacancies' => [[0, 0], [
            'مطلوب محاسب بخبرة سنتين' => [],
            'مطلوب موظف إدخال بيانات' => [],
            'مطلوب مسؤول مبيعات براتب وعمولة' => [],
            'مطلوب مدرس لغة إنجليزية' => [],
            'وظائف شاغرة لمهندسين مدنيين' => [],
        ]],
        'job-seekers' => [[0, 0], [
            'أبحث عن عمل في مجال المحاسبة' => [],
            'مبرمج ويب يبحث عن فرصة عمل' => [],
            'خريج تجارة يبحث عن وظيفة إدارية' => [],
        ]],
        'mobiles' => [[1_500, 65_000], [
            'آيفون 13 برو بحالة الجديد' => [],
            'سامسونج جالاكسي S22 بالضمان' => [],
            'شاومي ريدمي نوت 11 كسر زيرو' => [],
            'أوبو رينو 8 استعمال خفيف' => [],
        ]],
        'computers-and-laptops' => [[4_000, 90_000], [
            'لابتوب ديل كور i7 بحالة ممتازة' => [],
            'ماك بوك إير M1 نظيف' => [],
            'لابتوب جيمنج بكارت شاشة قوي' => [],
            'لابتوب لينوفو للطلبة' => [],
        ]],
        'tvs-and-screens' => [[3_000, 45_000], [
            'شاشة سامسونج 55 بوصة سمارت' => [1, 2, 4],
            'تلفزيون إل جي 4K بحالة الزيرو' => [1, 2, 3, 4],
            'شاشة كمبيوتر 27 بوصة' => [5, 6],
        ]],
        'cameras' => [[2_500, 80_000], [
            'كاميرا كانون 90D مع عدسة' => [1, 4],
            'كاميرا سوني A7 III بالكرتونة' => [2, 3, 6],
            'كاميرا ديجيتال احترافية للتصوير' => [1, 4, 5],
        ]],
        'video-games' => [[1_000, 30_000], [
            'بلايستيشن 5 مع دراعين' => [],
            'إكس بوكس سيريس إس جديد' => [],
            'ألعاب بلايستيشن 4 للبيع' => [],
        ]],
        'furniture' => [[800, 60_000], [
            'طقم كنب صالون مودرن' => [1, 2, 4, 5],
            'ركنة مودرن 6 مقاعد' => [1, 2, 4],
            'فوتيه عصري بحالة ممتازة' => [3],
            'ترابيزة خشب مع كرسي' => [6],
        ]],
        'home-appliances' => [[1_000, 40_000], [
            'غسالة أوتوماتيك 10 كيلو' => [3, 4],
            'مكنسة روبوت ذكية بحالة الزيرو' => [1],
            'ماكينة قهوة إسبريسو أوتوماتيك' => [2],
            'غسالة صناعية للمغاسل والمحلات' => [3],
        ]],
        'decor-and-housewares' => [[100, 8_000], [
            'مزهرية وشمعدانات للديكور' => [1, 6],
            'زهور مجففة بمزهرية خشب' => [2],
            'أباجورة وفوانيس ديكور' => [3, 4],
            'أصيص نباتات زينة للصالون' => [5],
        ]],
        'maintenance-and-finishing' => [[200, 20_000], [
            'فني تركيب وتثبيت بالدريل' => [4],
            'نقاش ودهانات بأسعار مناسبة' => [1, 2, 3],
            'فني كهرباء وتأسيس' => [6, 7, 8],
            'تركيب سيراميك وبورسلين' => [5],
        ]],
        'transport-and-shipping' => [[300, 6_000], [
            'نقل أثاث بسيارات مجهزة' => [1],
            'خدمة شحن بضائع بين المحافظات' => [1, 2, 3, 6],
            'رفع وتحميل بضائع بالونش' => [4, 5],
        ]],
        'lessons-and-courses' => [[100, 3_000], [
            'دروس خصوصية رياضيات للثانوية' => [],
            'كورس برمجة للمبتدئين أونلاين' => [],
            'مدرس لغة عربية للمرحلة الإعدادية' => [],
        ]],
        'events-and-photography' => [[1_500, 25_000], [
            'تصوير فوتوغرافي لحفلات الزفاف' => [1, 3, 4],
            'تنظيم حفلات أعياد ميلاد' => [5, 6],
            'تصوير فيديو احترافي للمناسبات' => [1, 2, 6],
        ]],
        'other-services' => [[100, 5_000], [
            'تنظيف واجهات زجاجية للمباني' => [1, 2],
            'برمجة وتطوير مواقع ويب' => [4],
            'تصميم شعارات وهويات تجارية' => [3, 5],
        ]],
        'clothes' => [[100, 4_000], [
            'بدلة رجالي إيطالي جديدة' => [1, 5, 6],
            'فستان سهرة استعمال مرة واحدة' => [4, 3],
            'جاكيت رجالي شتوي' => [2, 5],
            'ملابس حريمي موديلات جديدة' => [3, 4],
        ]],
        'shoes-and-bags' => [[150, 6_000], [
            'حذاء رياضي نايك أصلي' => [1, 3, 4],
            'شنطة يد جلد طبيعي' => [5, 6, 7, 9],
            'حذاء كونفرس أبيض بحالة ممتازة' => [2],
            'شنطة ظهر عملية للسفر' => [8],
        ]],
        'watches-and-jewelry' => [[400, 90_000], [
            'ساعة كاسيو أصلية' => [3, 1, 2],
            'خاتم ذهب عيار 21' => [5, 6, 7],
            'ساعة أوميجا بحالة الجديد' => [1],
            'ساعة ذكية أبل ووتش' => [4],
            'سلسلة ذهب بدلاية قلب' => [8],
        ]],
        'other' => [[50, 20_000], [
            'مجموعة كتب وقصص للأطفال' => [1],
            'إبريق نحاس أنتيك' => [2],
            'دولاب خشب منحوت قديم' => [3],
            'أصص نباتات زينة' => [4],
            'مقص وأدوات مكتبية' => [5],
            'فرش سيليكون للمطبخ' => [6],
        ]],
    ];

    private const LOCATIONS = [
        'شقة للبيع بمدينة نصر تشطيب سوبر لوكس' => ['القاهرة', 'مدينة نصر'],
        'شقة 150 متر بالتجمع الخامس' => ['القاهرة', 'التجمع الخامس'],
        'شقة بالمعادي قريبة من المترو' => ['القاهرة', 'المعادي'],
        'شقة تمليك بالهرم ناصية' => ['الجيزة', 'الهرم'],
        'شقة بمدينتي جاهزة للسكن' => ['القاهرة', 'مدينتي'],
        'شقة للإيجار بالمهندسين مفروشة' => ['الجيزة', 'المهندسين'],
        'شقة إيجار جديد بمدينة نصر' => ['القاهرة', 'مدينة نصر'],
        'شقة للإيجار بالشيخ زايد' => ['الجيزة', 'الشيخ زايد'],
        'استوديو للإيجار بالزمالك' => ['القاهرة', 'الزمالك'],
        'فيلا للبيع بالشيخ زايد حديقة خاصة' => ['الجيزة', 'الشيخ زايد'],
        'فيلا مستقلة بالتجمع الخامس' => ['القاهرة', 'التجمع الخامس'],
        'تاون هاوس بالرحاب جاهز للاستلام' => ['القاهرة', 'الرحاب'],
        'أرض للبيع بالعاشر من رمضان' => ['الشرقية', 'العاشر من رمضان'],
        'قطعة أرض سكنية بأكتوبر' => ['الجيزة', 'السادس من أكتوبر'],
        'أرض زراعية بطريق مصر إسكندرية' => ['البحيرة', null],
        'مكتب إداري للإيجار بوسط البلد' => ['القاهرة', 'وسط البلد'],
    ];

    private const ATTRIBUTES = [
        'تويوتا كورولا فبريكا بالكامل' => ['brand' => 'تويوتا', 'model' => 'كورولا', 'year' => 2019, 'mileage' => 65_000, 'condition' => 'مستعملة'],
        'هيونداي إلنترا حالة ممتازة' => ['brand' => 'هيونداي', 'model' => 'إلنترا', 'year' => 2021, 'mileage' => 38_000, 'condition' => 'مستعملة'],
        'كيا سبورتاج موديل حديث' => ['brand' => 'كيا', 'model' => 'سبورتاج', 'year' => 2023, 'mileage' => 12_000, 'condition' => 'مستعملة'],
        'نيسان صني بحالة الزيرو' => ['brand' => 'نيسان', 'model' => 'صني', 'year' => 2024, 'mileage' => 1_500, 'condition' => 'جديدة'],
        'شيفروليه أوبترا ماشية قليل' => ['brand' => 'شيفروليه', 'model' => 'أوبترا', 'year' => 2014, 'mileage' => 90_000, 'condition' => 'مستعملة'],
        'مرسيدس C180 فل الفل' => ['brand' => 'مرسيدس', 'model' => 'C180', 'year' => 2018, 'mileage' => 72_000, 'condition' => 'مستعملة', 'transmission' => 'أوتوماتيك'],
        'سيارة كلاسيك موديل قديم بحالة ممتازة' => ['brand' => 'أخرى', 'model' => 'كلاسيك', 'year' => 1974, 'mileage' => 150_000, 'condition' => 'مستعملة'],
        'سيارة للإيجار اليومي مع سائق' => ['brand' => 'تويوتا', 'model' => 'كورولا', 'year' => 2022, 'mileage' => null, 'condition' => 'مستعملة'],
        'إيجار سيارة كابريو لحفلات الزفاف' => ['brand' => 'أخرى', 'model' => 'كابريو', 'year' => 2020, 'mileage' => null, 'condition' => 'مستعملة'],
        'سيارة اقتصادية للإيجار الشهري' => ['brand' => 'هيونداي', 'model' => 'أكسنت', 'year' => 2021, 'mileage' => null, 'condition' => 'مستعملة'],
        'كاوتش جديد مقاس 16' => ['mileage' => null, 'condition' => 'جديدة'],
        'جنوط ألومنيوم مستعملة' => ['mileage' => null, 'condition' => 'مستعملة'],
        'طقم جنوط أصلي للمرسيدس' => ['brand' => 'مرسيدس', 'mileage' => null, 'condition' => 'مستعملة'],
        'عدادات ولوحة قيادة أصلية' => ['mileage' => null, 'condition' => 'مستعملة'],
        'ناقل حركة عصا مانيوال' => ['mileage' => null, 'transmission' => 'يدوي', 'condition' => 'مستعملة'],
        'موتوسيكل هوندا 2020 حالة ممتازة' => ['brand' => 'هوندا', 'model' => 'CB', 'year' => 2020, 'mileage' => 18_000, 'transmission' => 'يدوي', 'fuel_type' => 'بنزين', 'condition' => 'مستعملة'],
        'سكوتر فيسبا اقتصادي' => ['brand' => 'أخرى', 'model' => 'فيسبا', 'year' => 2019, 'mileage' => 22_000, 'transmission' => 'أوتوماتيك', 'fuel_type' => 'بنزين', 'condition' => 'مستعملة'],
        'دراجة نارية هارلي رخصة سارية' => ['brand' => 'أخرى', 'model' => 'هارلي ديفيدسون', 'year' => 2016, 'mileage' => 30_000, 'transmission' => 'يدوي', 'fuel_type' => 'بنزين', 'condition' => 'مستعملة'],
        'شقة للبيع بمدينة نصر تشطيب سوبر لوكس' => ['property_type' => 'شقة', 'area' => 130, 'rooms' => 3, 'bathrooms' => 2, 'floor' => 4, 'finishing' => 'تشطيب كامل'],
        'شقة 150 متر بالتجمع الخامس' => ['property_type' => 'شقة', 'area' => 150, 'rooms' => 3, 'bathrooms' => 2, 'floor' => 2, 'finishing' => 'تشطيب كامل'],
        'شقة بالمعادي قريبة من المترو' => ['property_type' => 'شقة', 'area' => 120, 'rooms' => 3, 'bathrooms' => 2, 'floor' => 5, 'finishing' => 'تشطيب كامل'],
        'شقة تمليك بالهرم ناصية' => ['property_type' => 'شقة', 'area' => 170, 'rooms' => 3, 'bathrooms' => 2, 'floor' => 3, 'finishing' => 'نصف تشطيب'],
        'شقة بمدينتي جاهزة للسكن' => ['property_type' => 'شقة', 'area' => 110, 'rooms' => 2, 'bathrooms' => 1, 'floor' => 6, 'finishing' => 'تشطيب كامل'],
        'شقة للإيجار بالمهندسين مفروشة' => ['property_type' => 'شقة', 'area' => 95, 'rooms' => 2, 'bathrooms' => 1, 'floor' => 3, 'finishing' => 'تشطيب كامل'],
        'شقة إيجار جديد بمدينة نصر' => ['property_type' => 'شقة', 'area' => 115, 'rooms' => 3, 'bathrooms' => 1, 'floor' => 2, 'finishing' => 'تشطيب كامل'],
        'شقة للإيجار بالشيخ زايد' => ['property_type' => 'شقة', 'area' => 140, 'rooms' => 3, 'bathrooms' => 2, 'floor' => 1, 'finishing' => 'تشطيب كامل'],
        'استوديو للإيجار بالزمالك' => ['property_type' => 'استوديو', 'area' => 55, 'rooms' => 1, 'bathrooms' => 1, 'floor' => 4, 'finishing' => 'تشطيب كامل'],
        'فيلا للبيع بالشيخ زايد حديقة خاصة' => ['property_type' => 'فيلا', 'area' => 450, 'rooms' => 5, 'bathrooms' => 4, 'floor' => null, 'finishing' => 'تشطيب كامل'],
        'فيلا مستقلة بالتجمع الخامس' => ['property_type' => 'فيلا', 'area' => 400, 'rooms' => 6, 'bathrooms' => 5, 'floor' => null, 'finishing' => 'تشطيب كامل'],
        'تاون هاوس بالرحاب جاهز للاستلام' => ['property_type' => 'تاون هاوس', 'area' => 250, 'rooms' => 4, 'bathrooms' => 3, 'floor' => null, 'finishing' => 'نصف تشطيب'],
        'أرض للبيع بالعاشر من رمضان' => ['property_type' => 'أرض', 'area' => 600, 'rooms' => null, 'bathrooms' => null, 'floor' => null, 'finishing' => null],
        'قطعة أرض سكنية بأكتوبر' => ['property_type' => 'أرض', 'area' => 300, 'rooms' => null, 'bathrooms' => null, 'floor' => null, 'finishing' => null],
        'أرض زراعية بطريق مصر إسكندرية' => ['property_type' => 'أرض', 'area' => 2_100, 'rooms' => null, 'bathrooms' => null, 'floor' => null, 'finishing' => null],
        'محل للبيع بموقع تجاري متميز' => ['property_type' => 'محل تجاري', 'area' => 60, 'rooms' => null, 'bathrooms' => 1, 'floor' => 0, 'finishing' => 'تشطيب كامل'],
        'مكتب إداري للإيجار بوسط البلد' => ['property_type' => 'مكتب', 'area' => 90, 'rooms' => 3, 'bathrooms' => 1, 'floor' => 5, 'finishing' => 'تشطيب كامل'],
        'محل ناصية على شارع رئيسي' => ['property_type' => 'محل تجاري', 'area' => 80, 'rooms' => null, 'bathrooms' => 1, 'floor' => 0, 'finishing' => 'تشطيب كامل'],
        'آيفون 13 برو بحالة الجديد' => ['brand' => 'أبل', 'condition' => 'مستعمل', 'warranty' => 0],
        'سامسونج جالاكسي S22 بالضمان' => ['brand' => 'سامسونج', 'condition' => 'جديد', 'warranty' => 1],
        'شاومي ريدمي نوت 11 كسر زيرو' => ['brand' => 'شاومي', 'condition' => 'مستعمل', 'warranty' => 0],
        'أوبو رينو 8 استعمال خفيف' => ['brand' => 'أوبو', 'condition' => 'مستعمل', 'warranty' => 0],
        'لابتوب ديل كور i7 بحالة ممتازة' => ['brand' => 'ديل', 'condition' => 'مستعمل', 'warranty' => 0],
        'ماك بوك إير M1 نظيف' => ['brand' => 'أبل', 'condition' => 'مستعمل', 'warranty' => 0],
        'لابتوب جيمنج بكارت شاشة قوي' => ['brand' => 'إم إس آي', 'condition' => 'مستعمل', 'warranty' => 1],
        'لابتوب لينوفو للطلبة' => ['brand' => 'لينوفو', 'condition' => 'مستعمل', 'warranty' => 0],
        'شاشة سامسونج 55 بوصة سمارت' => ['brand' => 'سامسونج', 'condition' => 'مستعمل', 'warranty' => 0],
        'تلفزيون إل جي 4K بحالة الزيرو' => ['brand' => 'إل جي', 'condition' => 'جديد', 'warranty' => 1],
        'شاشة كمبيوتر 27 بوصة' => ['brand' => 'ديل', 'condition' => 'مستعمل', 'warranty' => 0],
        'كاميرا كانون 90D مع عدسة' => ['brand' => 'كانون', 'condition' => 'مستعمل', 'warranty' => 0],
        'كاميرا سوني A7 III بالكرتونة' => ['brand' => 'سوني', 'condition' => 'جديد', 'warranty' => 1],
        'كاميرا ديجيتال احترافية للتصوير' => ['brand' => 'نيكون', 'condition' => 'مستعمل', 'warranty' => 0],
        'بلايستيشن 5 مع دراعين' => ['brand' => 'سوني', 'condition' => 'مستعمل', 'warranty' => 0],
        'إكس بوكس سيريس إس جديد' => ['brand' => 'مايكروسوفت', 'condition' => 'جديد', 'warranty' => 1],
        'ألعاب بلايستيشن 4 للبيع' => ['brand' => 'سوني', 'condition' => 'مستعمل', 'warranty' => 0],
        'بدلة رجالي إيطالي جديدة' => ['condition' => 'جديد', 'size' => '50'],
        'فستان سهرة استعمال مرة واحدة' => ['condition' => 'مستعمل', 'size' => 'M'],
        'حذاء رياضي نايك أصلي' => ['condition' => 'جديد', 'size' => '42'],
        'حذاء كونفرس أبيض بحالة ممتازة' => ['condition' => 'مستعمل', 'size' => '41'],
        'ساعة أوميجا بحالة الجديد' => ['condition' => 'جديد', 'size' => null],
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

    private array $imageCache = [];

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

        $listings = $this->createListings($users);
        $this->guaranteeFeaturedListings($listings);
        $this->createFavoritesAndReports($users, $listings);
        $this->createStoresAndSubscriptions($users);
        $this->createReviews($users);
        $this->createConversations($users, $listings);
        $this->createSavedSearches($users);
        $this->createAdBanners($users);

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

    private function imagesFor(string $categorySlug, array $numbers = []): array
    {
        $directory = database_path("seeders/data/images/{$categorySlug}");

        if (! is_dir($directory)) {
            $directory = database_path('seeders/data/images/other');
        }

        $files = $this->imageCache[$directory] ??= File::glob($directory.'/*.jpg');

        if ($numbers === []) {
            return $files;
        }

        return array_values(array_filter($files, fn (string $file) => in_array((int) basename($file, '.jpg'), $numbers, true)));
    }

    private function bannerImage(string $name): string
    {
        return database_path("seeders/data/images/banners/{$name}.jpg");
    }

    private function wideCropOf(string $path): string
    {
        $source = imagecreatefromjpeg($path);
        $width = imagesx($source);
        $height = min(imagesy($source), (int) round($width / 3.2));
        $top = (int) max(0, (imagesy($source) - $height) * 0.4);

        $target = imagecreatetruecolor($width, $height);
        imagecopy($target, $source, 0, 0, 0, $top, $width, $height);

        $directory = storage_path('app/demo-placeholders');
        File::ensureDirectoryExists($directory);

        $file = $directory.DIRECTORY_SEPARATOR.'promoted-banner.jpg';
        imagejpeg($target, $file, 84);

        return $file;
    }

    private function wikipedia(string $article): string
    {
        return 'https://ar.wikipedia.org/wiki/'.rawurlencode($article);
    }

    private function createListings(Collection $users): Collection
    {
        $leaves = Category::query()->whereDoesntHave('children')->get();
        $governorates = Governorate::with('cities')->get();
        $sync = app(SyncListingFieldValues::class);

        $created = collect();

        for ($n = 0; $n < static::$listingCount; $n++) {
            $category = $leaves->random();
            [$priceRange, $titles] = self::CATALOG[$category->slug] ?? self::CATALOG['other'];
            $title = Arr::random(array_keys($titles));
            $location = self::LOCATIONS[$title] ?? null;
            $governorate = $location === null ? null : $governorates->firstWhere('name', $location[0]);
            $governorate ??= $governorates->random();
            $city = match (true) {
                $location !== null && $location[1] !== null => $governorate->cities->firstWhere('name', $location[1]),
                $location !== null => null,
                default => $governorate->cities->isNotEmpty() && random_int(1, 10) > 3 ? $governorate->cities->random() : null,
            };
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

            $values = $sync($listing, $fields, $this->fieldValues($fields, $governorate->name, self::ATTRIBUTES[$title] ?? []));
            $listing->update(['search_text' => ListingSearchText::build($title, $description, $values)]);

            $pool = $this->imagesFor($category->slug, $titles[$title]);

            foreach (Arr::random($pool, random_int(1, min(3, count($pool)))) as $file) {
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

    private function fieldValues(Collection $fields, string $governorateName, array $overrides = []): array
    {
        $values = [];

        foreach ($fields as $field) {
            if (array_key_exists($field->key, $overrides)) {
                if ($overrides[$field->key] !== null) {
                    $values[$field->key] = (string) $overrides[$field->key];
                }

                continue;
            }

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

    private function createAdBanners(Collection $users): Collection
    {
        $advertiser = $users->first();

        $banners = [
            ['image' => $this->bannerImage('electronics'), 'placement' => 'home_top', 'title' => 'عرض المتجر الرقمي', 'target_url' => $this->wikipedia('تجارة_إلكترونية'), 'status' => AdBannerStatus::Active, 'starts_at' => now()->subDays(2), 'expires_at' => now()->addDays(5)],
            ['image' => $this->bannerImage('tourism'), 'placement' => 'home_top', 'title' => 'رحلات سياحية إلى الأهرامات', 'target_url' => $this->wikipedia('سياحة_في_مصر'), 'status' => AdBannerStatus::Active, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(12)],
            ['image' => $this->bannerImage('dental'), 'placement' => 'search_sidebar', 'title' => 'خصم عيادة الأسنان', 'target_url' => $this->wikipedia('طب_الأسنان'), 'status' => AdBannerStatus::Active, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(20)],
            ['image' => $this->bannerImage('movers'), 'placement' => 'listing_sidebar', 'title' => 'شركة نقل الأثاث', 'target_url' => $this->wikipedia('شاحنة'), 'status' => AdBannerStatus::Active, 'starts_at' => now()->subDays(3), 'expires_at' => now()->addDays(10)],
            ['image' => $this->bannerImage('restaurant'), 'placement' => 'home_top', 'title' => 'مطعم جديد بالتجمع', 'target_url' => $this->wikipedia('مطعم'), 'status' => AdBannerStatus::Pending],
            ['image' => $this->bannerImage('coins-sign'), 'placement' => 'search_sidebar', 'title' => 'إعلان صورته مخالفة', 'target_url' => $this->wikipedia('عملة_معدنية'), 'status' => AdBannerStatus::Rejected, 'rejection_reason' => 'الصورة تحتوي على نص كبير مخالف للسياسة.'],
        ];

        $promoted = Listing::query()->visible()->inRandomOrder()->first();

        if ($promoted !== null) {
            $categorySlug = Category::query()->whereKey($promoted->category_id)->value('slug');

            $banners[] = ['image' => $this->wideCropOf(Arr::first($this->imagesFor($categorySlug))), 'user_id' => $promoted->user_id, 'placement' => 'search_sidebar', 'title' => 'ترويج: '.Str::limit($promoted->title, 40), 'listing_id' => $promoted->id, 'status' => AdBannerStatus::Active, 'starts_at' => now()->subDay(), 'expires_at' => now()->addDays(14)];
        }

        $created = collect();

        foreach ($banners as $banner) {
            $existing = AdBanner::where('title', $banner['title'])->first();

            if ($existing) {
                $created->push($existing);

                continue;
            }

            $record = AdBanner::create(Arr::except($banner, 'image') + ['user_id' => $advertiser->id]);
            $record->addMedia($banner['image'])->preservingOriginal()->toMediaCollection(AdBanner::IMAGE);
            $created->push($record);
        }

        return $created;
    }
}
