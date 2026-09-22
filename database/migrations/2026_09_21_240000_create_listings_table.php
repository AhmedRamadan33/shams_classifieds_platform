<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained();
            $table->foreignId('governorate_id')->constrained();
            $table->foreignId('city_id')->nullable()->constrained()->nullOnDelete();

            $table->string('title', 150);
            $table->string('slug'); // deliberately NOT unique: the id identifies the listing, the slug is cosmetic
            $table->text('description');
            $table->text('search_text'); // Arabic-normalized title + description + field values

            $table->decimal('price', 12, 2)->nullable();
            $table->string('price_type', 20)->default('fixed'); // fixed | negotiable | free | contact
            $table->string('phone', 20);

            $table->string('status', 20)->default('pending'); // pending | active | rejected | expired | sold
            $table->text('rejection_reason')->nullable();

            $table->timestamp('published_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('expiry_reminded_at')->nullable();
            $table->timestamp('featured_until')->nullable();

            $table->unsignedInteger('views')->default(0);

            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'category_id', 'published_at']);
            $table->index(['status', 'governorate_id', 'published_at']);
            $table->index('expires_at');
            $table->index('featured_until');
            $table->fullText('search_text');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
