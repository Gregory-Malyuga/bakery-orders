<?php

namespace Domains\User\Domain\Database\Factories;

use Domains\User\Domain\Enums\UserRole;
use Domains\User\Domain\Enums\UserStatus;
use Domains\User\Domain\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => fake()->password(8),
            'role' => fake()->randomElement(array_column(UserRole::cases(), 'value')),
            'status' => UserStatus::Pending,
        ];
    }

    public function verified(): static
    {
        return $this->state(['status' => UserStatus::Verify]);
    }

    public function banned(): static
    {
        return $this->state(['status' => UserStatus::Banned]);
    }
}
