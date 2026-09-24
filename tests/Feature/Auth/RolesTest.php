<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\AdminSeeder;
use Database\Seeders\RoleSeeder;
use Spatie\Permission\Models\Role;

it('seeds the admin, moderator and user roles idempotently', function () {
    $this->seed(RoleSeeder::class);
    $this->seed(RoleSeeder::class);

    expect(Role::pluck('name')->sort()->values()->all())->toBe(['admin', 'moderator', 'user']);
});

it('gives every new user the user role', function () {
    $user = User::factory()->create();

    expect($user->hasRole('user'))->toBeTrue()
        ->and($user->isStaff())->toBeFalse();
});

it('seeds an administrator from the environment configuration', function () {
    config(['classifieds.admin.phone' => '01000000000', 'classifieds.admin.password' => 'secret-pass-123']);

    $this->seed(RoleSeeder::class);
    $this->seed(AdminSeeder::class);
    $this->seed(AdminSeeder::class);

    $admin = User::where('phone', '+201000000000')->sole();

    expect($admin->hasRole('admin'))->toBeTrue()
        ->and($admin->hasRole('user'))->toBeFalse()
        ->and($admin->hasVerifiedPhone())->toBeTrue()
        ->and(User::count())->toBe(1);

    $this->post('/login', ['phone' => '01000000000', 'password' => 'secret-pass-123']);
    $this->assertAuthenticatedAs($admin);
});

it('only lets admins and moderators into the /admin panel', function () {
    $this->actingAs(User::factory()->create())->get('/admin')->assertForbidden();

    $this->actingAs(User::factory()->moderator()->create())->get('/admin')->assertOk();
    $this->actingAs(User::factory()->admin()->create())->get('/admin')->assertOk();
});

it('keeps banned staff out of the panel', function () {
    $this->actingAs(User::factory()->admin()->banned()->create())->get('/admin')->assertForbidden();
});

it('redirects guests to the panel login, which takes a phone number or an e-mail', function () {
    $this->get('/admin')->assertRedirect('/admin/login');

    $this->get('/admin/login')->assertOk()->assertSee(__('app.auth.login_identifier'));
});
