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
