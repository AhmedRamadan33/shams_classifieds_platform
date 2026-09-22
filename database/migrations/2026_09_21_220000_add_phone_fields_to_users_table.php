<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone', 20)->unique()->after('name');
            $table->timestamp('phone_verified_at')->nullable()->after('phone');
            $table->boolean('is_banned')->default(false)->after('password');
            $table->string('avatar')->nullable()->after('is_banned');

            // The phone number is the login identifier; email becomes optional (still unique when present).
            $table->string('email')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['phone']);
            $table->dropColumn(['phone', 'phone_verified_at', 'is_banned', 'avatar']);
        });
    }
};
