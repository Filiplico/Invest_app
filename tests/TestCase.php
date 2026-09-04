<?php

namespace Tests;

use App\Models\Client;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function makeClient(?string $name = null): Client
    {
        return Client::factory()->create($name === null ? [] : ['name' => $name]);
    }

    protected function record(Client $client, array $payload): TestResponse
    {
        return $this->postJson("/api/clients/{$client->id}/transactions", $payload);
    }

    protected function fund(Client $client, float $amount): Client
    {
        $this->record($client, ['type' => 'deposit', 'amount' => $amount])->assertCreated();

        return $client;
    }

    protected function accountOf(Client $client): array
    {
        return $this->getJson("/api/clients/{$client->id}")->assertOk()->json('data');
    }
}
