<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reviewer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('seller_id')->constrained('users')->cascadeOnDelete();
            // Optional context ("about this listing"); kept if the listing is later deleted.
            $table->foreignId('listing_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->string('comment', 1000)->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            // One review per reviewer per seller; leaving a new one edits it instead of piling up.
            $table->unique(['reviewer_id', 'seller_id']);
            $table->index('seller_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
