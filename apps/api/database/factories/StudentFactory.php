<?php

namespace Database\Factories;

use App\Domains\Students\Models\Student;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Student>
 */
class StudentFactory extends Factory
{
    protected $model = Student::class;

    public function definition(): array
    {
        $name = fake()->name();
        $nim = fake()->unique()->numerify('2023##????');

        return [
            'tenant_id' => 'default',
            'source_system' => 'portal',
            'source_id' => (string) Str::uuid(),
            'nim' => $nim,
            'normalized_nim' => Str::lower($nim),
            'name' => $name,
            'normalized_name' => Str::lower($name),
            'institutional_email' => fake()->unique()->safeEmail(),
            'program_study_code' => 'D3KEP',
            'program_study_name' => 'D3 Keperawatan',
            'cohort_year' => 2023,
            'status' => 'active',
            'last_synced_at' => now(),
        ];
    }
}
