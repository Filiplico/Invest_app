<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreClientRequest;
use App\Models\Client;
use App\Services\AccountService;
use Illuminate\Http\JsonResponse;

class ClientController extends Controller
{
    public function __construct(private readonly AccountService $accounts)
    {
    }

    public function index(): JsonResponse
    {
        $clients = Client::orderBy('name')
            ->get()
            ->map(fn (Client $client): array => $this->basicDetails($client));

        return response()->json(['data' => $clients]);
    }

    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return response()->json(['data' => $this->basicDetails($client)], 201);
    }

    public function show(Client $client): JsonResponse
    {
        return response()->json([
            'data' => array_merge(
                $this->basicDetails($client),
                $this->accounts->summary($client)
            ),
        ]);
    }

    private function basicDetails(Client $client): array
    {
        return [
            'id' => $client->id,
            'name' => $client->name,
        ];
    }
}
