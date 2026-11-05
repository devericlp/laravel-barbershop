<?php

namespace Database\Seeders;

use App\Models\Service;
use Illuminate\Database\Seeder;

class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $services = [
            ['name' => 'Corte', 'description' => 'Corte na máquina e na tesoura, com acabamento na navalha.', 'price_cents' => 4500, 'duration_minutes' => 30],
            ['name' => 'Barba', 'description' => 'Barba modelada com toalha quente e navalha.', 'price_cents' => 3500, 'duration_minutes' => 30],
            ['name' => 'Corte + barba', 'description' => 'Corte completo e barba no mesmo atendimento.', 'price_cents' => 7000, 'duration_minutes' => 60],
            ['name' => 'Corte infantil', 'description' => 'Para crianças de até 12 anos.', 'price_cents' => 4000, 'duration_minutes' => 30],
            ['name' => 'Pezinho', 'price_cents' => 1500, 'duration_minutes' => 15],
            ['name' => 'Sobrancelha', 'price_cents' => 2000, 'duration_minutes' => 15],
            ['name' => 'Pigmentação', 'price_cents' => 5000, 'duration_minutes' => 45, 'is_active' => false],
        ];

        foreach ($services as $service) {
            Service::factory()->create($service);
        }
    }
}
