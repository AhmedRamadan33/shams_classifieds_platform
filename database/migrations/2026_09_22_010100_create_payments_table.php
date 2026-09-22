<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('listing_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained();
            $table->string('gateway', 20); // fake | paymob
            $table->decimal('amount', 10, 2);
            $table->string('currency', 3);
            $table->string('status', 20)->default('pending'); // pending | paid | failed | cancelled
            $table->string('gateway_order_id')->nullable(); // Paymob order id, used to match the webhook
            $table->string('gateway_transaction_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable(); // raw gateway response, for support/debugging
            $table->timestamps();

            $table->index('status');
            $table->index('gateway_order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};
