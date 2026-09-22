<?php

declare(strict_types=1);

use App\Models\Governorate;
use App\Models\Listing;
use App\Models\User;
use App\Services\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Fixtures;

beforeEach(function () {
    Storage::fake('public');
    config(['classifieds.require_review' => true]);

    $this->leaf = Fixtures::carsTree()['leaf'];
    $this->governorate = Governorate::factory()->create();
    $this->user = User::factory()->create(['phone' => '+201012345678']);

    $this->submit = fn (array $overrides = []) => $this->actingAs($this->user)
        ->post('/ads', Fixtures::listingPayload($this->leaf, $this->governorate, null, $overrides));
});

it('attaches images and generates thumb, medium and large WebP conversions', function () {
    ($this->submit)(['images' => [Fixtures::image('a.jpg', 2000, 1500), Fixtures::image('b.png', 900, 700)]])
        ->assertSessionHasNoErrors();

    $listing = Listing::with('media')->sole();
    $first = $listing->getFirstMedia(Listing::IMAGES);

    expect($listing->getMedia(Listing::IMAGES))->toHaveCount(2);

    foreach (['thumb' => 400, 'medium' => 800, 'large' => 1600] as $conversion => $max) {
        expect($first->hasGeneratedConversion($conversion))->toBeTrue();

        $path = $first->getPath($conversion);
        expect($path)->toEndWith('.webp');

        [$width, $height] = getimagesize($path);
        expect(max($width, $height))->toBeLessThanOrEqual($max)
            ->and(mime_content_type($path))->toBe('image/webp');
    }
});

it('never upscales small images in conversions', function () {
    ($this->submit)(['images' => [Fixtures::image('small.jpg', 300, 300)]]);

    $media = Listing::with('media')->sole()->getFirstMedia(Listing::IMAGES);

    [$width] = getimagesize($media->getPath('large'));
    expect($width)->toBe(300);
});

it('stores originals under generated names, never the user supplied file name', function () {
    ($this->submit)(['images' => [Fixtures::image('..%2F..%2Fevil name.jpg', 800, 600)]]);

    $media = Listing::with('media')->sole()->getFirstMedia(Listing::IMAGES);

    expect($media->file_name)->toMatch('/^[A-Za-z0-9]{24}\.jpg$/');
});

it('limits the number of images', function () {
    $images = collect(range(1, 9))->map(fn ($i) => Fixtures::image("img{$i}.jpg"))->all();

    ($this->submit)(['images' => $images])->assertSessionHasErrors('images');
    expect(Listing::count())->toBe(0);

    $ok = collect(range(1, 8))->map(fn ($i) => Fixtures::image("img{$i}.jpg", 300, 300))->all();
    ($this->submit)(['images' => $ok])->assertSessionHasNoErrors();
    expect(Listing::with('media')->sole()->getMedia(Listing::IMAGES))->toHaveCount(8);
});

it('rejects oversized files, non images and wrong dimensions', function (UploadedFile $file) {
    ($this->submit)(['images' => [$file]])->assertSessionHasErrors('images.0');
    expect(Listing::count())->toBe(0);
})->with([
    'over 5MB' => [fn () => UploadedFile::fake()->image('big.jpg', 800, 600)->size(6000)],
    'pdf' => [fn () => UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf')],
    'text renamed to jpg' => [fn () => UploadedFile::fake()->createWithContent('fake.jpg', 'this is not an image')],
    'gif' => [fn () => UploadedFile::fake()->image('anim.gif', 400, 400)],
    'too small' => [fn () => UploadedFile::fake()->image('tiny.jpg', 50, 50)],
    'too large dimensions' => [fn () => UploadedFile::fake()->image('huge.jpg', 9000, 300)],
]);

it('puts the chosen cover image first', function () {
    ($this->submit)([
        'images' => [Fixtures::image('a.jpg', 400, 300), Fixtures::image('b.jpg', 500, 400), Fixtures::image('c.jpg', 600, 500)],
        'cover' => 'new:2',
    ])->assertSessionHasNoErrors();

    $listing = Listing::with('media')->sole();
    $ordered = $listing->getMedia(Listing::IMAGES);

    expect($ordered)->toHaveCount(3)
        ->and(getimagesize($ordered->first()->getPath())[0])->toBe(600)
        ->and($listing->getFirstMediaUrl(Listing::IMAGES))->toBe($ordered->first()->getUrl());
});

it('keeps the upload order when no cover is chosen', function () {
    ($this->submit)(['images' => [Fixtures::image('a.jpg', 400, 300), Fixtures::image('b.jpg', 500, 400)]]);

    $ordered = Listing::with('media')->sole()->getMedia(Listing::IMAGES);

    expect(getimagesize($ordered->first()->getPath())[0])->toBe(400);
});

it('strips EXIF metadata and applies the orientation to the stored original', function () {
    $upload = Fixtures::jpegWithExifOrientation(120, 60);

    // Sanity check of the fixture: the EXIF block is really readable before sanitizing.
    expect(@exif_read_data($upload->getRealPath())['Orientation'] ?? null)->toBe(6);

    $clean = app(ImageSanitizer::class)->sanitize($upload);

    // GD only writes its own JPEG comment: no EXIF/IFD0/GPS sections and no Orientation tag survive.
    $after = @exif_read_data($clean['path']);
    $sections = is_array($after) ? (string) ($after['SectionsFound'] ?? '') : '';

    expect($after['Orientation'] ?? null)->toBeNull()
        ->and($sections)->not->toContain('EXIF')->not->toContain('IFD0')->not->toContain('GPS')
        ->and(getimagesize($clean['path'])[0])->toBe(60)   // 120x60 rotated by orientation 6
        ->and(getimagesize($clean['path'])[1])->toBe(120);

    @unlink($clean['path']);
});

it('refuses files that are not decodable images', function () {
    $fake = UploadedFile::fake()->createWithContent('x.jpg', 'not an image at all');

    app(ImageSanitizer::class)->sanitize($fake);
})->throws(InvalidArgumentException::class);
