<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExpenseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => 'required|exists:assets,id',
            'category' => 'required|string|max:255',
            'amount' => 'required|numeric|min:0',
            'currency' => 'required|string|size:3',
            'date' => 'required|date',
            'note' => 'nullable|string|max:500',
        ];
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'asset_id.required' => 'Asset is required',
            'asset_id.exists' => 'Selected asset does not exist',
            'category.required' => 'Category is required',
            'amount.required' => 'Amount is required',
            'amount.numeric' => 'Amount must be a number',
            'amount.min' => 'Amount must be greater than or equal to 0',
            'currency.required' => 'Currency is required',
            'currency.size' => 'Currency must be 3 characters (e.g., JPY, USD)',
            'date.required' => 'Date is required',
            'date.date' => 'Invalid date format',
        ];
    }

    /**
     * Verify asset ownership before validation
     */
    protected function prepareForValidation()
    {
        // Additional logic can be added here
    }
}
