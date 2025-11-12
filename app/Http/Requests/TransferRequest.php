<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class TransferRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
        return [
            'source_wallet_id' => 'required|integer|different:target_wallet_id|exists:wallets,id',
            'target_wallet_id' => 'required|integer|exists:wallets,id',
            'amount' => 'required',
            'metadata' => 'nullable|array'
        ];
    }

    public function messages()
    {
        return [
            'source_wallet_id.different' => 'Source and target wallets must be different',
        ];
    }
}
