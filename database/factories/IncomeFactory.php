<?php

namespace Database\Factories;

use App\Models\Income;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class IncomeFactory extends Factory
{
    protected $model = Income::class;

    public function definition(): array
    {
        return [
            'owner_type' => User::class,
            'owner_id' => User::factory(),
            'asset_id' => Asset::factory(),
            'category' => $this->faker->randomElement(['給料', 'ボーナス', 'その他']),
            'amount' => $this->faker->numberBetween(1000, 50000),
            'currency' => 'JPY',
            'date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'note' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
