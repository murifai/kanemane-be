<?php

namespace Database\Factories;

use App\Models\Expense;
use App\Models\Asset;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseFactory extends Factory
{
    protected $model = Expense::class;

    public function definition(): array
    {
        return [
            'owner_type' => User::class,
            'owner_id' => User::factory(),
            'asset_id' => Asset::factory(),
            'category' => $this->faker->randomElement(['食費', '交通費', '娯楽', 'その他']),
            'amount' => $this->faker->numberBetween(100, 10000),
            'currency' => 'JPY',
            'date' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'note' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
        ];
    }
}
