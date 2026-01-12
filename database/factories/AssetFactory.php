<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'owner_type' => User::class,
            'owner_id' => User::factory(),
            'country' => $this->faker->randomElement(['JP', 'ID']),
            'name' => $this->faker->randomElement(['Bank Account', 'E-Money', 'Investment']),
            'type' => $this->faker->randomElement(['tabungan', 'e-money', 'investasi']),
            'currency' => $this->faker->randomElement(['JPY', 'IDR']),
            'balance' => $this->faker->numberBetween(1000, 100000),
        ];
    }
}
