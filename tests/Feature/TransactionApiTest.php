<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TransactionApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_deposit_increases_the_cash_balance(): void
    {
        $client = $this->makeClient();

        $this->record($client, ['type' => 'deposit', 'amount' => 1000])
            ->assertCreated()
            ->assertJsonPath('account.cash_balance', '1000.00');
    }

    public function test_a_withdrawal_decreases_the_cash_balance(): void
    {
        $client = $this->fund($this->makeClient(), 1000);

        $this->record($client, ['type' => 'withdrawal', 'amount' => 250.50])
            ->assertCreated()
            ->assertJsonPath('account.cash_balance', '749.50');
    }

    public function test_a_purchase_reduces_cash_and_adds_holdings(): void
    {
        $client = $this->fund($this->makeClient(), 1000);

        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100])
            ->assertCreated()
            ->assertJsonPath('account.cash_balance', '500.00')
            ->assertJsonPath('account.holdings', [['instrument' => 'AAPL', 'quantity' => 5]]);
    }

    public function test_a_sale_returns_cash_and_reduces_holdings(): void
    {
        $client = $this->fund($this->makeClient(), 1000);
        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100]);

        $this->record($client, ['type' => 'sell', 'instrument' => 'AAPL', 'quantity' => 3, 'price_per_unit' => 120])
            ->assertCreated()
            ->assertJsonPath('account.cash_balance', '860.00')
            ->assertJsonPath('account.holdings', [['instrument' => 'AAPL', 'quantity' => 2]]);
    }

    public function test_the_amount_of_a_trade_is_calculated_from_quantity_and_price(): void
    {
        $client = $this->fund($this->makeClient(), 1000);

        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 3, 'price_per_unit' => 99.99])
            ->assertCreated()
            ->assertJsonPath('data.amount', '299.97');
    }

    public function test_a_sale_may_use_a_different_price_than_the_purchase(): void
    {
        $client = $this->fund($this->makeClient(), 500);
        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100]);

        $this->record($client, ['type' => 'sell', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 40])
            ->assertCreated()
            ->assertJsonPath('account.cash_balance', '200.00');
    }

    public function test_a_fully_sold_instrument_disappears_from_holdings(): void
    {
        $client = $this->fund($this->makeClient(), 1000);
        $this->record($client, ['type' => 'buy', 'instrument' => 'NVDA', 'quantity' => 20, 'price_per_unit' => 50]);
        $this->record($client, ['type' => 'sell', 'instrument' => 'NVDA', 'quantity' => 20, 'price_per_unit' => 65]);

        $this->assertSame([], $this->accountOf($client)['holdings']);
    }

    public function test_holdings_are_tracked_separately_per_instrument(): void
    {
        $client = $this->fund($this->makeClient(), 5000);
        $this->record($client, ['type' => 'buy', 'instrument' => 'MSFT', 'quantity' => 10, 'price_per_unit' => 300]);
        $this->record($client, ['type' => 'buy', 'instrument' => 'TSLA', 'quantity' => 4, 'price_per_unit' => 250]);

        $this->assertSame([
            ['instrument' => 'MSFT', 'quantity' => 10],
            ['instrument' => 'TSLA', 'quantity' => 4],
        ], $this->accountOf($client)['holdings']);
    }

    public function test_it_returns_the_full_ledger_for_a_client(): void
    {
        $client = $this->fund($this->makeClient(), 1000);
        $this->record($client, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100]);

        $this->getJson("/api/clients/{$client->id}/transactions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'deposit')
            ->assertJsonPath('data.1.type', 'buy')
            ->assertJsonPath('data.1.amount', '500.00');
    }

    public function test_recorded_movements_cannot_be_changed_or_deleted(): void
    {
        $client = $this->fund($this->makeClient(), 1000);
        $transaction = $client->transactions()->firstOrFail();

        $this->expectException(RuntimeException::class);

        $transaction->update(['amount' => 999999]);
    }

    public function test_the_scenario_from_the_specification(): void
    {
        $ana = $this->makeClient('Ana');

        $this->record($ana, ['type' => 'deposit', 'amount' => 1000])->assertCreated();
        $this->record($ana, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100])->assertCreated();

        $account = $this->accountOf($ana);
        $this->assertSame('500.00', $account['cash_balance']);
        $this->assertSame([['instrument' => 'AAPL', 'quantity' => 5]], $account['holdings']);

        $this->record($ana, ['type' => 'buy', 'instrument' => 'AAPL', 'quantity' => 7, 'price_per_unit' => 100])->assertStatus(422);
        $this->record($ana, ['type' => 'sell', 'instrument' => 'AAPL', 'quantity' => 8, 'price_per_unit' => 120])->assertStatus(422);

        $this->record($ana, ['type' => 'sell', 'instrument' => 'AAPL', 'quantity' => 3, 'price_per_unit' => 120])->assertCreated();

        $account = $this->accountOf($ana);
        $this->assertSame('860.00', $account['cash_balance']);
        $this->assertSame([['instrument' => 'AAPL', 'quantity' => 2]], $account['holdings']);
    }
}
