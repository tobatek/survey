<?php

namespace Database\Seeders;

use App\Domains\Identity\Models\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (app()->environment('local', 'testing')) {
            $admin = User::factory()->create([
                'email' => 'admin@myameta-survey.test',
                'display_name' => 'Admin LPM (seed lokal)',
            ]);

            $admin->roles()->attach(Role::query()->where('code', Role::LPM_ADMIN)->first());
        }
    }
}
