<?php

namespace Database\Seeders;

use App\Models\Professional;
use App\Models\Service;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProfessionalSeeder extends Seeder
{
    /** @param array<string, array{services: list<string>}> $professionals */
    public function run(string $domain, array $professionals): void
    {
        foreach ($professionals as $name => $professional) {
            $user = User::factory()->professional()->create([
                'name' => $name,
                'email' => Str::slug(Str::before($name, ' ')).'@'.$domain,
            ]);

            Professional::factory()
                ->create(['user_id' => $user->id])
                ->services()
                ->attach(Service::whereIn('name', $professional['services'])->pluck('id'));
        }
    }
}
