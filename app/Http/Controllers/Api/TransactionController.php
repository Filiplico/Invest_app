<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Client;
use App\Models\Transaction;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;

class TransactionController extends Controller
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function index(Client $client): JsonResponse
    {
        $transactions = $client->transactions()
            ->orderBy('id')
            ->get()
            ->map(fn (Transaction $transaction): array => $this->details($transaction));

        return response()->json(['data' => $transactions]);
    }

    public function store(StoreTransactionRequest $request, Client $client): JsonResponse
    {
        $transaction = $this->accounts->record(
            $client,
            $request->transactionType(),
            $request->validated()
        );

        return response()->json([
            'data' => $this->details($transaction),
            'account' => $this->accounts->summary($client),
        ], 201);
    }

    private function details(Transaction $transaction): array
    {
        return [
            'id' => $transaction->id,
            'type' => $transaction->type->value,
            'amount' => $transaction->amount,
            'instrument' => $transaction->instrument,
            'quantity' => $transaction->quantity,
            'price_per_unit' => $transaction->price_per_unit,
            'recorded_at' => $transaction->created_at->toIso8601String(),
        ];
    }
}
