<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Income;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_dashboard_summary_returns_correct_structure()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'total_assets_jpy',
                'total_assets_idr',
                'monthly_income',
                'monthly_expense',
                'balance',
                'top_expense_category',
            ]);
    }

    public function test_dashboard_summary_calculates_totals_correctly()
    {
        // Create assets
        Asset::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'currency' => 'JPY',
            'balance' => 10000,
        ]);

        Asset::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'currency' => 'IDR',
            'balance' => 50000,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/summary');

        $response->assertStatus(200)
            ->assertJson([
                'total_assets_jpy' => 10000,
                'total_assets_idr' => 50000,
            ]);
    }

    public function test_dashboard_charts_returns_correct_structure()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/charts');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'monthly_trend' => [
                    '*' => ['month', 'income', 'expense']
                ],
                'category_breakdown' => [
                    '*' => ['category', 'amount']
                ],
            ]);
    }

    public function test_dashboard_charts_returns_6_months_of_data()
    {
        $response = $this->actingAs($this->user)
            ->getJson('/api/dashboard/charts');

        $response->assertStatus(200);
        $data = $response->json();
        
        $this->assertCount(6, $data['monthly_trend']);
    }
}
