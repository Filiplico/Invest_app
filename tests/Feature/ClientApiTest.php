<?php

namespace Tests\Feature;

use App\Models\Client;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ClientApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_client(): void
    {
        $this->postJson('/api/clients', ['name' => 'Ana Petrova'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Ana Petrova');

        $this->assertDatabaseHas('clients', ['name' => 'Ana Petrova']);
    }

    public function test_it_rejects_a_client_without_a_name(): void
    {
        $this->postJson('/api/clients', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_it_rejects_a_duplicate_client_name(): void
    {
        $this->makeClient('Ana Petrova');

        $this->postJson('/api/clients', ['name' => 'Ana Petrova'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('name');
    }

    public function test_a_new_client_starts_with_no_cash_and_no_holdings(): void
    {
        $account = $this->accountOf($this->makeClient());

        $this->assertSame('0.00', $account['cash_balance']);
        $this->assertSame([], $account['holdings']);
    }

    public function test_it_lists_every_client(): void
    {
        Client::factory()->count(3)->create();

        $this->getJson('/api/clients')
            ->assertOk()
            ->assertJsonCount(3, 'data');
    }

    public function test_it_returns_a_json_404_for_an_unknown_client(): void
    {
        $this->getJson('/api/clients/999')
            ->assertNotFound()
            ->assertJsonPath('error', 'not_found');
    }
}
