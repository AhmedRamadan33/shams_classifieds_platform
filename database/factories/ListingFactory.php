<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ListingStatus;
use App\Enums\PriceType;
use App\Models\Category;
use App\Models\Governorate;
use App\Models\Listing;
use App\Models\User;
use App\Services\ArabicText;
use App\Services\ListingSearchText;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Listing>
 */
class ListingFactory extends Factory
{
    /**
     * Active by default, so tests and demo data are visible on the public site.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->randomElement([
            'شقة للبيع في مدينة نصر', 'سيارة تويوتا كورولا موديل حديث', 'لابتوب ديل بحالة ممتازة',
            'موبايل سامسونج جديد بالضمان', 'أريكة مودرن ثلاث مقاعد', 'مطلوب محاسب بخبرة',
            'خدمة صيانة تكييف وتبريد', 'فستان سهرة جديد', 'دراجة نارية بحالة الزيرو',
        ]).' '.fake()->numerify('###');

        $description = fake()->randomElement([
            'الإعلان بحالة ممتازة ولم يُستخدم كثيراً، والسعر قابل للنقاش مع الجادين فقط.',
            'للتواصل والمعاينة يرجى الاتصال على الرقم الموضح، المعاينة متاحة طوال أيام الأسبوع.',
            'تفاصيل كاملة عند الاتصال، جميع الأوراق سليمة ومتوفرة وجاهز للتسليم فوراً.',
        ]).' '.fake()->sentence(6);

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'governorate_id' => Governorate::factory(),
            'city_id' => null,
            'title' => $title,
            'slug' => ArabicText::slug($title),
            'description' => $description,
            'search_text' => ListingSearchText::build($title, $description, collect()),
            'price' => fake()->numberBetween(500, 2_000_000),
            'price_type' => PriceType::Fixed,
            'phone' => '+2010'.fake()->numerify('########'),
            'status' => ListingStatus::Active,
            'published_at' => now()->subDays(fake()->numberBetween(0, 10)),
            'expires_at' => now()->addDays(20),
        ];
    }

    public function pending(): static
    {
        return $this->state(fn () => [
            'status' => ListingStatus::Pending,
            'published_at' => null,
            'expires_at' => null,
        ]);
    }

    public function rejected(string $reason = 'الإعلان مخالف لسياسة الموقع.'): static
    {
        return $this->state(fn () => [
            'status' => ListingStatus::Rejected,
            'rejection_reason' => $reason,
            'published_at' => null,
            'expires_at' => null,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'status' => ListingStatus::Expired,
            'published_at' => now()->subDays(40),
            'expires_at' => now()->subDays(5),
        ]);
    }

    public function sold(): static
    {
        return $this->state(fn () => ['status' => ListingStatus::Sold]);
    }

    public function featured(): static
    {
        return $this->state(fn () => ['featured_until' => now()->addDays(7)]);
    }

    public function titled(string $title, ?string $description = null): static
    {
        return $this->state(function (array $attributes) use ($title, $description) {
            $description ??= $attributes['description'];

            return [
                'title' => $title,
                'slug' => ArabicText::slug($title),
                'description' => $description,
                'search_text' => ListingSearchText::build($title, $description, collect()),
            ];
        });
    }
}
