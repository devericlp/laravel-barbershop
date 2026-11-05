<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => config('app.admin_email'),
            'password' => config('app.admin_password'),
        ]);

        $this->call(TenantSeeder::class);
    }
}
