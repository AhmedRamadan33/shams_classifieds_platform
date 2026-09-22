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
            // In-app notifications always fire; these two add e-mail and WhatsApp copies of the same
            // events (listing approved/rejected, new message, expiring soon, saved search matches).
            // E-mail needs an address on the profile; WhatsApp uses the verified phone number.
            $table->boolean('notify_email')->default(false)->after('avatar');
            $table->boolean('notify_whatsapp')->default(false)->after('notify_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['notify_email', 'notify_whatsapp']);
        });
    }
};
