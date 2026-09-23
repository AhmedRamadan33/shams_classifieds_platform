<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ad_banners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ad_package_id')->nullable()->constrained()->nullOnDelete();
            $table->string('placement');
            $table->string('title')->nullable();
            $table->string('target_url');
            $table->string('status')->default('pending');
            $table->string('rejection_reason', 1000)->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('clicks')->default(0);
            $table->timestamps();

            $table->index(['placement', 'status', 'expires_at']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ad_banners');
    }
};
