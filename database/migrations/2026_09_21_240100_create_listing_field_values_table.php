<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_field_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_field_id')->constrained()->cascadeOnDelete();
            $table->string('value');
            $table->timestamps();

            $table->unique(['listing_id', 'category_field_id']);
            $table->index(['category_field_id', 'value']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_field_values');
    }
};
