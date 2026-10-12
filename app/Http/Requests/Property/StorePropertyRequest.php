<?php

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;

class StorePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('properties.create')
            ?? false;
    }

    public function rules(): array
    {
        return [
            'nwsc_account' => ['sometimes', 'required', 'array:account_number,account_name,meter_number,phone,status'],
            'nwsc_account.account_number' => ['required_with:nwsc_account', 'string', 'max:255'],
            'nwsc_account.account_name' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nwsc_account.meter_number' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nwsc_account.phone' => ['sometimes', 'nullable', 'string', 'max:255'],
            'nwsc_account.status' => ['sometimes', 'required', 'in:active,inactive,suspended'],

            'organization_id' => [
                'nullable',
                'integer',
                'exists:organizations,id',
            ],

            'name' => [
                'required',
                'string',
                'max:255',
            ],

            'property_code' => [
                'required',
                'string',
                'max:100',
                'unique:properties,property_code',
            ],

            'address' => [
                'nullable',
                'string',
            ],

            'city' => [
                'nullable',
                'string',
                'max:100',
            ],

            'district' => [
                'nullable',
                'string',
                'max:100',
            ],

            'latitude' => [
                'nullable',
                'numeric',
                'between:-90,90',
            ],

            'longitude' => [
                'nullable',
                'numeric',
                'between:-180,180',
            ],

            'status' => [
                'nullable',
                'in:active,inactive,suspended',
            ],
        ];
    }
}
