<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Asset;
use App\Models\Income;
use App\Models\Expense;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AssetApiTest extends TestCase
{
    use RefreshDatabase;

    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_list_assets()
    {
        Asset::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->getJson('/api/assets');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'personal' => [
                    '*' => ['id', 'name', 'type', 'currency', 'balance']
                ],
                'family'
            ]);
    }

    public function test_can_create_asset()
    {
        $response = $this->actingAs($this->user)
            ->postJson('/api/assets', [
                'name' => 'Test Bank',
                'type' => 'tabungan',
                'country' => 'JP',
                'currency' => 'JPY',
                'balance' => 10000,
            ]);

        $response->assertStatus(201)
            ->assertJsonStructure(['id', 'name', 'type', 'currency', 'balance']);

        $this->assertDatabaseHas('assets', [
            'name' => 'Test Bank',
            'balance' => 10000,
        ]);
    }

    public function test_can_update_asset()
    {
        $asset = Asset::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
            'name' => 'Old Name',
        ]);

        $response = $this->actingAs($this->user)
            ->putJson("/api/assets/{$asset->id}", [
                'name' => 'New Name',
            ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('assets', [
            'id' => $asset->id,
            'name' => 'New Name',
        ]);
    }

    public function test_can_delete_asset()
    {
        $asset = Asset::factory()->create([
            'owner_type' => User::class,
            'owner_id' => $this->user->id,
        ]);

        $response = $this->actingAs($this->user)
            ->deleteJson("/api/assets/{$asset->id}");

        $response->assertStatus(200);
        $this->assertDatabaseMissing('assets', ['id' => $asset->id]);
    }
}
