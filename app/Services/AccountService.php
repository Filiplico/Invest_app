<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Exceptions\InsufficientFundsException;
use App\Exceptions\InsufficientHoldingsException;
use App\Models\Client;
use App\Models\Transaction;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AccountService
{
    public function balance(Client $client): float
    {
        $balance = $client->transactions()
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type IN (?, ?) THEN amount ELSE -amount END), 0) AS balance',
                [TransactionType::Deposit->value, TransactionType::Sell->value]
            )
            ->value('balance');

        return round((float) $balance, 2);
    }

    public function holdings(Client $client): Collection
    {
        return $client->transactions()
            ->whereNotNull('instrument')
            ->groupBy('instrument')
            ->orderBy('instrument')
            ->selectRaw(
                'instrument, SUM(CASE WHEN type = ? THEN quantity ELSE -quantity END) AS quantity',
                [TransactionType::Buy->value]
            )
            ->get()
            ->map(fn ($row): array => [
                'instrument' => $row->instrument,
                'quantity' => (int) $row->quantity,
            ])
            ->filter(fn (array $holding): bool => $holding['quantity'] > 0)
            ->values();
    }

    public function summary(Client $client): array
    {
        return [
            'cash_balance' => number_format($this->balance($client), 2, '.', ''),
            'holdings' => $this->holdings($client),
        ];
    }

    public function unitsOwned(Client $client, string $instrument): int
    {
        $quantity = $client->transactions()
            ->where('instrument', $instrument)
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN type = ? THEN quantity ELSE -quantity END), 0) AS quantity',
                [TransactionType::Buy->value]
            )
            ->value('quantity');

        return (int) $quantity;
    }

    public function record(Client $client, TransactionType $type, array $data): Transaction
    {
        return DB::transaction(function () use ($client, $type, $data): Transaction {
            Client::whereKey($client->getKey())->lockForUpdate()->first();

            $attributes = $this->buildAttributes($type, $data);

            $this->assertRulesAreSatisfied($client, $type, $attributes);

            return $client->transactions()->create($attributes);
        });
    }

    private function buildAttributes(TransactionType $type, array $data): array
    {
        if (! $type->isTrade()) {
            return [
                'type' => $type,
                'amount' => round((float) $data['amount'], 2),
                'instrument' => null,
                'quantity' => null,
                'price_per_unit' => null,
            ];
        }

        $quantity = (int) $data['quantity'];
        $pricePerUnit = round((float) $data['price_per_unit'], 2);

        return [
            'type' => $type,
            'amount' => round($quantity * $pricePerUnit, 2),
            'instrument' => $data['instrument'],
            'quantity' => $quantity,
            'price_per_unit' => $pricePerUnit,
        ];
    }

    private function assertRulesAreSatisfied(Client $client, TransactionType $type, array $attributes): void
    {
        if (! $type->addsCash()) {
            $balance = $this->balance($client);

            if ($attributes['amount'] > $balance) {
                throw InsufficientFundsException::make($balance, $attributes['amount']);
            }
        }

        if ($type === TransactionType::Sell) {
            $owned = $this->unitsOwned($client, $attributes['instrument']);

            if ($attributes['quantity'] > $owned) {
                throw InsufficientHoldingsException::make(
                    $attributes['instrument'],
                    $owned,
                    $attributes['quantity']
                );
            }
        }
    }
}
