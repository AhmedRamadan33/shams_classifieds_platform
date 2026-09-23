<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('listing_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['listing_id', 'type', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listing_events');
    }
};
