<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TransactionValidationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rejects_a_missing_type(): void
    {
        $this->record($this->makeClient(), ['amount' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_it_rejects_an_unknown_type(): void
    {
        $this->record($this->makeClient(), ['type' => 'gift', 'amount' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('type');
    }

    public function test_it_rejects_a_zero_amount(): void
    {
        $this->record($this->makeClient(), ['type' => 'deposit', 'amount' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_it_rejects_a_negative_amount(): void
    {
        $this->record($this->makeClient(), ['type' => 'deposit', 'amount' => -100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_it_rejects_a_deposit_without_an_amount(): void
    {
        $this->record($this->makeClient(), ['type' => 'deposit'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_it_rejects_an_amount_with_more_than_two_decimals(): void
    {
        $this->record($this->makeClient(), ['type' => 'deposit', 'amount' => 10.999])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_it_rejects_a_purchase_without_an_instrument(): void
    {
        $this->record($this->makeClient(), ['type' => 'buy', 'quantity' => 5, 'price_per_unit' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('instrument');
    }

    public function test_it_rejects_a_fractional_quantity(): void
    {
        $this->record($this->makeClient(), ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 1.5, 'price_per_unit' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    public function test_it_rejects_a_quantity_below_one(): void
    {
        $this->record($this->makeClient(), ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 0, 'price_per_unit' => 100])
            ->assertStatus(422)
            ->assertJsonValidationErrors('quantity');
    }

    public function test_it_rejects_a_zero_price_per_unit(): void
    {
        $this->record($this->makeClient(), ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors('price_per_unit');
    }

    public function test_it_rejects_an_amount_supplied_on_a_trade(): void
    {
        $this->record($this->makeClient(), ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100, 'amount' => 1])
            ->assertStatus(422)
            ->assertJsonValidationErrors('amount');
    }

    public function test_it_rejects_an_instrument_supplied_on_a_deposit(): void
    {
        $this->record($this->makeClient(), ['type' => 'deposit', 'amount' => 100, 'instrument' => 'AAPL'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('instrument');
    }

    public function test_no_movement_is_stored_when_validation_fails(): void
    {
        $client = $this->makeClient();

        $this->record($client, ['type' => 'deposit', 'amount' => -100])->assertStatus(422);

        $this->assertSame(0, $client->transactions()->count());
    }
}
