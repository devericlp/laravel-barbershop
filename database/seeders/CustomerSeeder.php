<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Customer;
use App\Models\Scopes\TenantScope;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerSeeder extends Seeder
{
    public function run(string $domain): void
    {
        $this->registerOtherBarbershopsDemoCustomers($domain);

        Customer::factory()->create([
            'user_id' => User::factory()->customer()->create([
                'name' => 'Cliente Demonstração',
                'email' => 'cliente@'.$domain,
            ])->id,
        ]);

        Customer::factory()->count(16)->create();
        Customer::factory()->withoutUser()->count(8)->create();

        $this->registerAsCustomer(User::withRole(UserRole::Manager)->firstOrFail());
        $this->registerAsCustomer(User::withRole(UserRole::Professional)->firstOrFail());
    }

    private function registerOtherBarbershopsDemoCustomers(string $domain): void
    {
        User::withoutGlobalScope(TenantScope::class)
            ->where('email', 'like', 'cliente@%')
            ->where('email', '!=', 'cliente@'.$domain)
            ->each(fn (User $user) => Customer::factory()->create(['user_id' => $user->id]));
    }

    private function registerAsCustomer(User $user): void
    {
        $user->assignRole(UserRole::Customer->value);
        Customer::factory()->create(['user_id' => $user->id]);
    }
}
