<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        $ids = DB::table('media')->where('model_type', 'App\Models\HeroSlide')->pluck('id');

        foreach ($ids as $id) {
            Storage::disk((string) config('media-library.disk_name'))->deleteDirectory((string) $id);
        }

        DB::table('media')->whereIn('id', $ids)->delete();

        Schema::dropIfExists('hero_slides');
    }

    public function down(): void
    {
        Schema::create('hero_slides', function (Blueprint $table) {
            $table->id();
            $table->string('title')->nullable();
            $table->string('subtitle')->nullable();
            $table->string('link_url')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });
    }
};
