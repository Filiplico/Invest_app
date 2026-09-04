<?php

namespace Database\Seeders;

use App\Enums\TransactionType;
use App\Models\Client;
use App\Services\AccountService;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(AccountService $accounts): void
    {
        $ana = Client::create(['name' => 'Ana Petrova']);
        $accounts->record($ana, TransactionType::Deposit, ['amount' => 1000]);
        $accounts->record($ana, TransactionType::Buy, ['instrument' => 'AAPL', 'quantity' => 5, 'price_per_unit' => 100]);
        $accounts->record($ana, TransactionType::Sell, ['instrument' => 'AAPL', 'quantity' => 3, 'price_per_unit' => 120]);

        $marko = Client::create(['name' => 'Marko Ilievski']);
        $accounts->record($marko, TransactionType::Deposit, ['amount' => 5000]);
        $accounts->record($marko, TransactionType::Buy, ['instrument' => 'MSFT', 'quantity' => 10, 'price_per_unit' => 300]);
        $accounts->record($marko, TransactionType::Buy, ['instrument' => 'TSLA', 'quantity' => 4, 'price_per_unit' => 250]);
        $accounts->record($marko, TransactionType::Withdrawal, ['amount' => 500]);

        $elena = Client::create(['name' => 'Elena Stojanova']);
        $accounts->record($elena, TransactionType::Deposit, ['amount' => 2000]);
        $accounts->record($elena, TransactionType::Buy, ['instrument' => 'NVDA', 'quantity' => 20, 'price_per_unit' => 50]);
        $accounts->record($elena, TransactionType::Sell, ['instrument' => 'NVDA', 'quantity' => 20, 'price_per_unit' => 65]);
    }
}
