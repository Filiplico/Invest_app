<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BusinessRuleTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_withdrawal_above_the_balance_is_rejected(): void
    {
        $client = $this->fund($this->makeClient(), 100);

        $this->record($client, ['type' => 'withdrawal', 'amount' => 100.01])
            ->assertStatus(422)
            ->assertJsonPath('error', 'insufficient_funds');
    }

    public function test_a_purchase_above_the_balance_is_rejected(): void
    {
        $client = $this->fund($this->makeClient(), 500);

        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 7, 'price_per_unit' => 100])
            ->assertStatus(422)
            ->assertJsonPath('error', 'insufficient_funds');
    }

    public function test_a_sale_above_the_owned_quantity_is_rejected(): void
    {
        $client = $this->fund($this->makeClient(), 1000);
        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100]);

        $this->record($client, ['type' => 'sell', 'instrument' => 'AAPL', 'quantity' => 8, 'price_per_unit' => 120])
            ->assertStatus(422)
            ->assertJsonPath('error', 'insufficient_holdings');
    }

    public function test_an_instrument_that_was_never_owned_cannot_be_sold(): void
    {
        $client = $this->fund($this->makeClient(), 1000);

        $this->record($client, ['type' => 'sell', 'instrument' => 'TSLA', 'quantity' => 1, 'price_per_unit' => 200])
            ->assertStatus(422)
            ->assertJsonPath('error', 'insufficient_holdings');
    }

    public function test_a_rejected_movement_leaves_the_account_untouched(): void
    {
        $client = $this->fund($this->makeClient(), 1000);
        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100]);

        $before = $this->accountOf($client);

        $this->record($client, ['type' => 'withdrawal', 'amount' => 5000])->assertStatus(422);
        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 7, 'price_per_unit' => 100])->assertStatus(422);
        $this->record($client, ['type' => 'sell', 'instrument' => 'AAPL', 'quantity' => 8, 'price_per_unit' => 120])->assertStatus(422);

        $this->assertSame($before, $this->accountOf($client));
        $this->assertSame(2, $client->transactions()->count());
    }

    public function test_a_client_may_spend_the_entire_balance(): void
    {
        $client = $this->fund($this->makeClient(), 500);

        $this->record($client, ['type' => 'withdrawal', 'amount' => 500])
            ->assertCreated()
            ->assertJsonPath('account.cash_balance', '0.00');
    }

    public function test_a_client_may_sell_the_entire_holding(): void
    {
        $client = $this->fund($this->makeClient(), 500);
        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100]);

        $this->record($client, ['type' => 'sell', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100])
            ->assertCreated();
    }

    public function test_one_client_cannot_spend_another_clients_money(): void
    {
        $rich = $this->fund($this->makeClient('Rich'), 10000);
        $poor = $this->makeClient('Poor');

        $this->record($poor, ['type' => 'withdrawal', 'amount' => 100])
            ->assertStatus(422)
            ->assertJsonPath('error', 'insufficient_funds');

        $this->assertSame('10000.00', $this->accountOf($rich)['cash_balance']);
    }

    public function test_one_client_cannot_sell_another_clients_holdings(): void
    {
        $owner = $this->fund($this->makeClient('Owner'), 1000);
        $this->record($owner, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100]);

        $stranger = $this->fund($this->makeClient('Stranger'), 1000);

        $this->record($stranger, ['type' => 'sell', 'instrument' => 'AAPL', 'quantity' => 1, 'price_per_unit' => 100])
            ->assertStatus(422)
            ->assertJsonPath('error', 'insufficient_holdings');
    }
}
