<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TransactionType::class)],

            'amount' => [
                'required_if:type,deposit,withdrawal',
                'prohibited_if:type,buy,sell',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:999999999999.99',
            ],

            'instrument' => [
                'required_if:type,buy,sell',
                'prohibited_if:type,deposit,withdrawal',
                'string',
                'max:50',
            ],

            'quantity' => [
                'required_if:type,buy,sell',
                'prohibited_if:type,deposit,withdrawal',
                'integer',
                'min:1',
            ],

            'price_per_unit' => [
                'required_if:type,buy,sell',
                'prohibited_if:type,deposit,withdrawal',
                'numeric',
                'gt:0',
                'decimal:0,2',
                'max:999999999999.99',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'amount.prohibited_if' => 'The amount of a buy or sell is calculated from quantity and price per unit.',
            'instrument.prohibited_if' => 'Only a buy or a sell refers to an instrument.',
            'quantity.prohibited_if' => 'Only a buy or a sell has a quantity.',
            'price_per_unit.prohibited_if' => 'Only a buy or a sell has a price per unit.',
        ];
    }

    public function transactionType(): TransactionType
    {
        return TransactionType::from($this->validated('type'));
    }
}
