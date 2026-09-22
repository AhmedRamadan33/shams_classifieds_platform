<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A payment now pays for one of two things: a featured-listing package (listing_id + package_id,
 * as before) or a store subscription (subscription_id). Exactly one of the two is set; enforced in
 * code (App\Actions\InitiateFeaturedPayment / InitiateSubscriptionPayment), not by the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->foreignId('listing_id')->nullable()->change();
            $table->foreignId('package_id')->nullable()->change();
            $table->foreignId('subscription_id')->nullable()->after('package_id')->constrained()->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('subscription_id');
            $table->foreignId('listing_id')->nullable(false)->change();
            $table->foreignId('package_id')->nullable(false)->change();
        });
    }
};
