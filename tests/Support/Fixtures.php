<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\FieldType;
use App\Models\Category;
use App\Models\CategoryField;
use App\Models\City;
use App\Models\Governorate;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Shared builders for the listing tests.
 */
final class Fixtures
{
    /**
     * A "cars" parent with a leaf child. Parent fields: brand (select, required), model (text),
     * year (number, required, filterable), mileage (number, unit كم), warranty (boolean).
     *
     * @return array{parent: Category, leaf: Category}
     */
    public static function carsTree(): array
    {
        $parent = Category::factory()->create(['name' => 'سيارات', 'slug' => 'cars']);
        $leaf = Category::factory()->childOf($parent)->create(['name' => 'سيارات للبيع', 'slug' => 'cars-for-sale']);

        self::field($parent, 'brand', 'الماركة', FieldType::Select, 1, required: true, filterable: true, options: ['تويوتا', 'هيونداي', 'كيا']);
        self::field($parent, 'model', 'الموديل', FieldType::Text, 2);
        self::field($parent, 'year', 'سنة الصنع', FieldType::Number, 3, required: true, filterable: true);
        self::field($parent, 'mileage', 'الكيلومترات', FieldType::Number, 4, filterable: true, unit: 'كم');
        self::field($parent, 'warranty', 'الضمان', FieldType::Boolean, 5, filterable: true);

        return ['parent' => $parent, 'leaf' => $leaf];
    }

    public static function field(
        Category $category,
        string $key,
        string $name,
        FieldType $type,
        int $order = 0,
        bool $required = false,
        bool $filterable = false,
        ?array $options = null,
        ?string $unit = null,
    ): CategoryField {
        return CategoryField::create([
            'category_id' => $category->id,
            'name' => $name,
            'key' => $key,
            'type' => $type,
            'options' => $options,
            'unit' => $unit,
            'is_required' => $required,
            'is_filterable' => $filterable,
            'sort_order' => $order,
        ]);
    }

    /**
     * A verified user plus a valid submission for a cars listing.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function listingPayload(Category $leaf, Governorate $governorate, ?City $city = null, array $overrides = []): array
    {
        return array_replace_recursive([
            'category_id' => $leaf->id,
            'governorate_id' => $governorate->id,
            'city_id' => $city?->id,
            'title' => 'تويوتا كورولا 2020 بحالة ممتازة',
            'description' => 'سيارة تويوتا كورولا موديل 2020 بحالة ممتازة جداً، صيانة دورية بالتوكيل.',
            'price_type' => 'fixed',
            'price' => '450000',
            'phone' => '01012345678',
            'fields' => ['brand' => 'تويوتا', 'model' => 'كورولا', 'year' => '2020', 'mileage' => '55000', 'warranty' => '1'],
        ], $overrides);
    }

    public static function user(): User
    {
        return User::factory()->create();
    }

    public static function image(string $name = 'photo.jpg', int $width = 800, int $height = 600): UploadedFile
    {
        return UploadedFile::fake()->image($name, $width, $height);
    }

    /**
     * A real JPEG that carries an EXIF block with Orientation = 6 (rotate 90° clockwise).
     */
    public static function jpegWithExifOrientation(int $width, int $height): UploadedFile
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 60, 30));

        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        // APP1 segment: "Exif\0\0" + little-endian TIFF header + one IFD entry (Orientation = 6).
        $exif = "\xFF\xE1\x00\x22Exif\x00\x00II\x2A\x00\x08\x00\x00\x00\x01\x00\x12\x01\x03\x00\x01\x00\x00\x00\x06\x00\x00\x00\x00\x00\x00\x00";

        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'exif-'.bin2hex(random_bytes(6)).'.jpg';
        file_put_contents($path, substr($jpeg, 0, 2).$exif.substr($jpeg, 2));

        return new UploadedFile($path, 'exif.jpg', 'image/jpeg', null, true);
    }
}
