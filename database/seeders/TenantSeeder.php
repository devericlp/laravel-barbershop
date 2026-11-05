<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use App\Support\CurrentTenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    public function run(): void
    {
        $tenants = [
            [
                'name' => 'Corte & Hora',
                'domain' => 'corteehora.com',
                'professionals' => [
                    'Rafael Mendes' => [
                        'services' => ['Corte', 'Barba', 'Corte + barba', 'Pezinho'],
                        'days' => [2, 3, 4, 5, 6],
                        'intervals' => [['09:00', '12:00'], ['13:00', '19:00']],
                    ],
                    'Lucas Andrade' => [
                        'services' => ['Corte', 'Corte infantil', 'Pezinho', 'Sobrancelha'],
                        'days' => [1, 2, 3, 4, 5],
                        'intervals' => [['10:00', '14:00'], ['15:00', '20:00']],
                    ],
                    'Thiago Rocha' => [
                        'services' => ['Corte', 'Barba', 'Corte + barba', 'Sobrancelha', 'Pigmentação'],
                        'days' => [3, 4, 5, 6],
                        'intervals' => [['09:00', '13:00'], ['14:00', '18:00']],
                    ],
                ],
            ],
            [
                'name' => 'Navalha de Ouro',
                'domain' => 'navalhadeouro.com',
                'professionals' => [
                    'Bruno Carvalho' => [
                        'services' => ['Corte', 'Barba', 'Corte + barba'],
                        'days' => [1, 2, 3, 4, 5, 6],
                        'intervals' => [['08:00', '12:00'], ['13:00', '17:00']],
                    ],
                    'Diego Martins' => [
                        'services' => ['Corte', 'Corte infantil', 'Sobrancelha'],
                        'days' => [2, 3, 4, 5, 6],
                        'intervals' => [['11:00', '15:00'], ['16:00', '21:00']],
                    ],
                ],
            ],
        ];

        foreach ($tenants as $data) {
            $tenant = Tenant::factory()->create(['name' => $data['name']]);

            app(CurrentTenant::class)->set($tenant);

            User::factory()->manager()->create([
                'name' => 'Gerente '.$data['name'],
                'email' => 'gerente@'.$data['domain'],
            ]);

            $this->call(ServiceSeeder::class);
            $this->callWith(ProfessionalSeeder::class, ['domain' => $data['domain'], 'professionals' => $data['professionals']]);
            $this->callWith(WorkingHourSeeder::class, ['professionals' => $data['professionals']]);
            $this->callWith(CustomerSeeder::class, ['domain' => $data['domain']]);
            $this->call(BookingSeeder::class);
        }

        app(CurrentTenant::class)->set(null);
    }
}
