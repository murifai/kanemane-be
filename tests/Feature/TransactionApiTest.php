<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Income;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;
    protected $asset;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
        $this->asset = Asset::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'balance' => 10000,
        ]);
    }

    public function test_creating_income_increases_asset_balance()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/transactions/income', [
                'asset_id' => $this->asset->id,
                'amount' => 500,
                'category' => '給料',
                'date' => now()->toDateString(),
            ]);

        $response->assertStatus(201);
        $this->assertEquals(10500, $this->asset->fresh()->balance);
    }

    public function test_creating_expense_decreases_asset_balance()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/transactions/expense', [
                'asset_id' => $this->asset->id,
                'amount' => 500,
                'category' => '食費',
                'date' => now()->toDateString(),
            ]);

        $response->assertStatus(201);
        $this->assertEquals(9500, $this->asset->fresh()->balance);
    }

    public function test_cannot_create_expense_with_insufficient_balance()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/transactions/expense', [
                'asset_id' => $this->asset->id,
                'amount' => 20000, // More than balance
                'category' => '食費',
                'date' => now()->toDateString(),
            ]);

        $response->assertStatus(400)
            ->assertJson(['error' => 'Insufficient balance']);
    }

    public function test_deleting_income_reverses_balance_change()
    {
        $income = Income::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'asset_id' => $this->asset->id,
            'amount' => 500,
        ]);

        $this->asset->increment('balance', 500);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/transactions/{$income->id}");

        $response->assertStatus(200);
        $this->assertEquals(10000, $this->asset->fresh()->balance);
    }

    public function test_deleting_expense_reverses_balance_change()
    {
        $expense = Expense::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'asset_id' => $this->asset->id,
            'amount' => 500,
        ]);

        $this->asset->decrement('balance', 500);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/transactions/{$expense->id}");

        $response->assertStatus(200);
        $this->assertEquals(10000, $this->asset->fresh()->balance);
    }

    public function test_can_list_transactions()
    {
        Income::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'asset_id' => $this->asset->id,
        ]);

        Expense::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'asset_id' => $this->asset->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/transactions');

        $response->assertStatus(200)
            ->assertJsonCount(2);
    }
}
