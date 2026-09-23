<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use App\Services\PhoneNormalizer;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $phone = config('classifieds.admin.phone');
        $password = config('classifieds.admin.password');

        if (blank($phone) || blank($password)) {
            $this->command?->warn('ADMIN_PHONE / ADMIN_PASSWORD are not set: no administrator was created.');

            return;
        }

        $admin = User::firstOrNew(['phone' => app(PhoneNormalizer::class)->normalize((string) $phone)]);

        $admin->fill([
            'name' => config('classifieds.admin.name'),
            'password' => $password,
            'phone_verified_at' => $admin->phone_verified_at ?? now(),
            'is_banned' => false,
        ])->save();

        $admin->syncRoles(User::ROLE_ADMIN);
    }
}
