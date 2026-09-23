<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('saved_searches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('category_slug', 100)->nullable();
            $table->string('governorate_slug', 100)->nullable();
            $table->json('filters')->nullable();
            $table->boolean('notify')->default(false);
            $table->timestamp('last_notified_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index('notify');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('saved_searches');
    }
};
