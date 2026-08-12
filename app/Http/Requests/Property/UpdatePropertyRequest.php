<?php

namespace App\Http\Requests\Property;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePropertyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('properties.update')
            ?? false;
    }

    public function rules(): array
    {
        $propertyId =
            $this->route('property')?->id;

        return [
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:255',
            ],

            'property_code' => [
                'sometimes',
                'required',
                'string',
                'max:100',
                Rule::unique(
                    'properties',
                    'property_code'
                )->ignore($propertyId),
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
                'sometimes',
                'required',
                'in:active,inactive,suspended',
            ],
        ];
    }
}