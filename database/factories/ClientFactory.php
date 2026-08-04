<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ClientStatus;
use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Client>
 */
class ClientFactory extends Factory
{
    protected $model = Client::class;

    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'name' => fake()->company(),
            'email' => fake()->unique()->safeEmail(),
            'status' => ClientStatus::Active,
            'notes' => null,
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (): array => ['status' => ClientStatus::Blocked]);
    }
}
