<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ad_banners', function (Blueprint $table) {
            $table->foreignId('listing_id')->nullable()->after('ad_package_id')->constrained()->nullOnDelete();
            $table->string('target_url')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('ad_banners')->whereNull('target_url')->update(['target_url' => '']);

        Schema::table('ad_banners', function (Blueprint $table) {
            $table->dropConstrainedForeignId('listing_id');
            $table->string('target_url')->nullable(false)->change();
        });
    }
};
