<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => 'default',
            'hub_user_id' => (string) Str::uuid(),
            'email' => fake()->unique()->safeEmail(),
            'display_name' => fake()->name(),
            'status' => 'active',
            'last_login_at' => now(),
            'last_synced_at' => now(),
        ];
    }
}
