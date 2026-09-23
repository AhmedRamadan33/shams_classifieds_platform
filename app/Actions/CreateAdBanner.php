<?php

declare(strict_types=1);

namespace App\Actions;

use App\Enums\AdBannerStatus;
use App\Enums\AdPlacement;
use App\Models\AdBanner;
use App\Models\User;
use App\Services\ImageSanitizer;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;

final class CreateAdBanner
{
    public function __construct(private readonly ImageSanitizer $sanitizer) {}

    public function __invoke(User $user, AdPlacement $placement, string $targetUrl, ?string $title, UploadedFile $image): AdBanner
    {
        $banner = AdBanner::create([
            'user_id' => $user->id,
            'placement' => $placement->value,
            'title' => $title,
            'target_url' => $targetUrl,
            'status' => AdBannerStatus::Pending,
        ]);

        $clean = $this->sanitizer->sanitize($image);

        $banner->addMedia($clean['path'])
            ->usingName($banner->title ?? $placement->label())
            ->usingFileName(Str::random(24).'.'.$clean['extension'])
            ->toMediaCollection(AdBanner::IMAGE);

        return $banner;
    }
}
