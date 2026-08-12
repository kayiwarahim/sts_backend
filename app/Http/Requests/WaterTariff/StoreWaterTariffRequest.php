<?php

namespace App\Http\Requests\WaterTariff;

use Illuminate\Foundation\Http\FormRequest;

class StoreWaterTariffRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            'water_tariffs.create'
        ) ?? false;
    }

    public function rules(): array
    {
        return [
            'property_id' => [
                'required',
                'integer',
                'exists:properties,id',
            ],

            'name' => [
                'required',
                'string',
                'max:100',
            ],

            'price_per_m3' => [
                'required',
                'numeric',
                'min:0',
            ],

            'currency' => [
                'nullable',
                'string',
                'size:3',
            ],

            'effective_from' => [
                'required',
                'date',
            ],

            'effective_to' => [
                'nullable',
                'date',
                'after_or_equal:effective_from',
            ],

            'status' => [
                'nullable',
                'in:active,inactive',
            ],

            'notes' => [
                'nullable',
                'string',
            ],
        ];
    }
}